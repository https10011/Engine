<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Api;

use CasinoEngine\Api\ApiKernel;
use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Database\ConnectionInterface;
use CasinoEngine\Persistence\Repository\ProbabilityRepository;
use CasinoEngine\Persistence\Repository\TokenRepository;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Services\Auth\AuthService;
use CasinoEngine\Tests\Support\DatabaseTestCase;

/**
 * The administrator probability surface, through the real router.
 *
 * The security tests matter most here. An endpoint that changes every game's
 * odds is the most valuable thing in the application, so these assert that a
 * player token cannot reach it, that a body cannot name its own administrator,
 * and that the role is read from the database rather than from the token.
 */
final class AdminApiTest extends DatabaseTestCase
{
    private ApiKernel $kernel;
    private int $adminId;
    private string $adminToken;
    private string $playerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernel = new ApiKernel($this->engine);
        $this->adminId = $this->createUserWithPassword('rootadmin', 'correct-horse-battery-staple');
        $this->createUserWithPassword('player1', 'another-long-password-here');
        $this->adminToken = $this->login('rootadmin', 'correct-horse-battery-staple');
        $this->playerToken = $this->login('player1', 'another-long-password-here');

        $users = $this->engine->container()->get(UserRepository::class);
        $users->setRole($this->adminId, 'admin', $this->clock()->now()->format('Y-m-d H:i:s'));
    }

    // ---------------------------------------------------------------- helpers

    private function createUserWithPassword(string $username, string $password): int
    {
        $users = $this->engine->container()->get(UserRepository::class);
        $now = $this->clock()->now()->format('Y-m-d H:i:s');
        $userId = $users->create($username, ucfirst($username), $now);

        $auth = new AuthService(
            $users,
            $this->engine->container()->get(TokenRepository::class),
            $this->engine->container()->get(ClockInterface::class)
        );
        $users->setCredentials($userId, $auth->hashPassword($password), PASSWORD_ARGON2ID, $now);

        $connection = $this->engine->container()->get(ConnectionInterface::class);
        $statement = $connection->pdo()->prepare(
            'INSERT INTO wallets (user_id, currency, balance_minor, created_at, updated_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute([$userId, 'PHP', 100000]);

        return $userId;
    }

    private function login(string $username, string $password): string
    {
        $response = $this->post('/api/v1/auth/login', ['username' => $username, 'password' => $password]);
        self::assertSame(200, $response->status, $response->body());

        return (string) $response->data['token'];
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    private function post(string $path, array $body = [], array $headers = []): Response
    {
        return $this->kernel->handle(new Request('POST', $path, $headers, [], $body));
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $query
     */
    private function get(string $path, array $headers = [], array $query = []): Response
    {
        return $this->kernel->handle(new Request('GET', $path, $headers, $query));
    }

    /** @return array<string, string> */
    private function asAdmin(): array
    {
        return ['authorization' => 'Bearer ' . $this->adminToken];
    }

    /** @return array<string, string> */
    private function asPlayer(): array
    {
        return ['authorization' => 'Bearer ' . $this->playerToken];
    }

    // --------------------------------------------------------------- security

    public function testAdminRoutesRequireAToken(): void
    {
        $response = $this->get('/api/v1/admin/overview');

        self::assertSame(401, $response->status, $response->body());
    }

    public function testAPlayerTokenCannotReachTheAdminSurface(): void
    {
        $paths = [
            '/api/v1/admin/overview',
            '/api/v1/admin/probability/global',
            '/api/v1/admin/probability/games',
            '/api/v1/admin/audit',
        ];

        foreach ($paths as $path) {
            $response = $this->get($path, $this->asPlayer());

            self::assertSame(403, $response->status, "GET $path should reject a player token");
        }

        $post = $this->post('/api/v1/admin/probability/global', ['enabled' => true], $this->asPlayer());
        self::assertSame(403, $post->status, 'A player must not be able to write configuration.');
        $simulate = $this->post('/api/v1/admin/simulate', ['game_id' => 'x', 'rounds' => 500], $this->asPlayer());
        self::assertSame(403, $simulate->status, 'A player must not be able to run the simulation tool.');
    }

    public function testAPlayerCannotSaveGlobalProbability(): void
    {
        $response = $this->post(
            '/api/v1/admin/probability/global',
            ['enabled' => true, 'any_win_target' => 0.9],
            $this->asPlayer()
        );

        self::assertSame(403, $response->status);

        $repository = $this->engine->container()->get(ProbabilityRepository::class);
        self::assertNull($repository->findProfile('global'), 'A rejected request must not have written a profile.');
    }

    public function testRevokingTheRoleTakesEffectOnTheNextRequest(): void
    {
        self::assertSame(200, $this->get('/api/v1/admin/overview', $this->asAdmin())->status);

        $users = $this->engine->container()->get(UserRepository::class);
        $users->setRole($this->adminId, 'player', $this->clock()->now()->format('Y-m-d H:i:s'));

        self::assertSame(
            403,
            $this->get('/api/v1/admin/overview', $this->asAdmin())->status,
            'The role must be re-read from the database, not trusted from the token.'
        );
    }

    // ------------------------------------------------------------ global profile

    public function testGlobalProfileIsSavedVersionedAndAudited(): void
    {
        $response = $this->post('/api/v1/admin/probability/global', [
            'enabled' => true,
            'any_win_target' => 0.5,
            'rtp_target' => 0.96,
        ], $this->asAdmin());

        self::assertSame(200, $response->status, $response->body());
        self::assertSame(0.5, $response->data['profile']['any_win_target']);
        self::assertSame(1, $response->data['profile']['version']);

        $second = $this->post('/api/v1/admin/probability/global', [
            'enabled' => true,
            'any_win_target' => 0.45,
            'rtp_target' => 0.96,
        ], $this->asAdmin());

        self::assertSame(2, $second->data['profile']['version'], 'A second save is a new version.');

        $audit = $this->get('/api/v1/admin/audit', $this->asAdmin());
        self::assertGreaterThanOrEqual(2, count($audit->data['entries']));
        self::assertSame('rootadmin', $audit->data['entries'][0]['admin_username']);
        self::assertSame('global', $audit->data['entries'][0]['profile_name']);

        $versions = $this->get('/api/v1/admin/probability/versions', $this->asAdmin(), ['profile' => 'global']);
        self::assertSame(2, count($versions->data['versions']));
    }

    public function testAProbabilityOutsideItsRangeIsRejected(): void
    {
        $response = $this->post('/api/v1/admin/probability/global', [
            'enabled' => true,
            'any_win_target' => 5.0,
        ], $this->asAdmin());

        self::assertSame(422, $response->status, $response->body());
    }

    public function testAnUnknownConfigurationKeyIsRejected(): void
    {
        $response = $this->post('/api/v1/admin/probability/global', [
            'enabled' => true,
            'any_winn_target' => 0.5,
        ], $this->asAdmin());

        self::assertSame(422, $response->status, $response->body());
    }

    // ---------------------------------------------------------------- overrides

    public function testAnOverrideTakesPrecedenceOverTheGlobalProfile(): void
    {
        $this->post('/api/v1/admin/probability/global', [
            'enabled' => true,
            'any_win_target' => 0.30,
        ], $this->asAdmin());

        $gameId = $this->firstSlotId();

        $override = $this->post("/api/v1/admin/probability/games/$gameId", [
            'enabled' => true,
            'any_win_target' => 0.55,
        ], $this->asAdmin());

        self::assertSame(200, $override->status, $override->body());
        self::assertSame(0.55, $override->data['effective']['any_win_target']);
        self::assertSame('override', $override->data['source']);

        // A game without an override still resolves the global value.
        $rows = $this->get('/api/v1/admin/probability/games', $this->asAdmin());
        $byId = [];
        foreach ($rows->data['games'] as $row) {
            $byId[$row['game']['id']] = $row;
        }

        self::assertSame('override', $byId[$gameId]['source']);
        self::assertSame(0.55, $byId[$gameId]['effective']['any_win_target']);

        $other = $this->slotIds()[1] ?? null;
        if ($other !== null) {
            self::assertSame('global', $byId[$other]['source']);
            self::assertSame(0.30, $byId[$other]['effective']['any_win_target']);
        }
    }

    public function testResettingAnOverrideFallsBackToGlobal(): void
    {
        $this->post('/api/v1/admin/probability/global', ['enabled' => true, 'any_win_target' => 0.4], $this->asAdmin());

        $gameId = $this->firstSlotId();
        $this->post("/api/v1/admin/probability/games/$gameId", ['enabled' => true, 'any_win_target' => 0.8], $this->asAdmin());

        $reset = $this->post("/api/v1/admin/probability/games/$gameId/reset", [], $this->asAdmin());

        self::assertSame(200, $reset->status, $reset->body());
        self::assertSame('global', $reset->data['source']);
        self::assertSame(0.4, $reset->data['effective']['any_win_target']);
    }

    public function testAnOverrideForAnUnknownGameIsRejected(): void
    {
        $response = $this->post('/api/v1/admin/probability/games/not.a.game', [
            'enabled' => true,
            'any_win_target' => 0.5,
        ], $this->asAdmin());

        // The game id is resolved before anything is written, so an unknown id
        // is a missing resource rather than a validation failure.
        self::assertSame(404, $response->status, $response->body());
    }

    public function testRestoringAVersionCreatesANewVersionRatherThanRewritingHistory(): void
    {
        $this->post('/api/v1/admin/probability/global', ['enabled' => true, 'any_win_target' => 0.2], $this->asAdmin());
        $this->post('/api/v1/admin/probability/global', ['enabled' => true, 'any_win_target' => 0.7], $this->asAdmin());

        $restore = $this->post('/api/v1/admin/probability/restore', [
            'profile' => 'global',
            'version' => 1,
        ], $this->asAdmin());

        self::assertSame(200, $restore->status, $restore->body());
        self::assertSame(0.2, $restore->data['profile']['any_win_target']);
        self::assertSame(3, $restore->data['profile']['version']);

        $versions = $this->get('/api/v1/admin/probability/versions', $this->asAdmin(), ['profile' => 'global']);
        self::assertSame(3, count($versions->data['versions']), 'History is append-only.');
    }

    // -------------------------------------------------------------- simulation

    public function testSimulationReportsConfiguredAgainstObserved(): void
    {
        $gameId = $this->firstSlotId();

        $this->post('/api/v1/admin/probability/games/' . $gameId, [
            'enabled' => true,
            'any_win_target' => 0.5,
        ], $this->asAdmin());

        $response = $this->post('/api/v1/admin/simulate', [
            'game_id' => $gameId,
            'rounds' => 3000,
            'seed' => 4242,
        ], $this->asAdmin());

        self::assertSame(200, $response->status, $response->body());

        $observed = $response->data['observed'];
        self::assertSame(3000, $observed['rounds']);
        self::assertArrayHasKey('observed_hit_frequency', $observed);
        self::assertArrayHasKey('observed_rtp', $observed);

        // The configured figure must be reported next to, not instead of, the
        // observed one.
        self::assertSame(0.5, $response->data['comparison']['any_win']['configured']);
        self::assertNotNull($response->data['comparison']['any_win']['observed']);
    }

    public function testSimulationDoesNotTouchTheWalletOrRoundHistory(): void
    {
        $connection = $this->engine->container()->get(ConnectionInterface::class);
        $before = (int) $connection->pdo()->query('SELECT COUNT(*) FROM game_rounds')->fetchColumn();

        $this->post('/api/v1/admin/simulate', [
            'game_id' => $this->firstSlotId(),
            'rounds' => 500,
        ], $this->asAdmin());

        $after = (int) $connection->pdo()->query('SELECT COUNT(*) FROM game_rounds')->fetchColumn();
        self::assertSame($before, $after, 'A simulation must never write a round row.');
    }

    public function testASimulationRoundCountOutsideTheRangeIsRejected(): void
    {
        $response = $this->post('/api/v1/admin/simulate', [
            'game_id' => $this->firstSlotId(),
            'rounds' => 10,
        ], $this->asAdmin());

        self::assertSame(422, $response->status, $response->body());
    }

    public function testASeededSimulationIsReproducible(): void
    {
        $gameId = $this->firstSlotId();
        $body = ['game_id' => $gameId, 'rounds' => 1000, 'seed' => 99];

        $first = $this->post('/api/v1/admin/simulate', $body, $this->asAdmin());
        $second = $this->post('/api/v1/admin/simulate', $body, $this->asAdmin());

        self::assertSame(
            $first->data['observed']['total_returned_minor'],
            $second->data['observed']['total_returned_minor'],
            'The same seed must produce the same stream.'
        );
    }

    // ------------------------------------------------------------ round integrity

    public function testAConfigurationChangeDoesNotAlterAnInFlightSession(): void
    {
        $create = $this->post('/api/v1/sessions', ['game_id' => $this->firstSlotId()], $this->asPlayer());
        self::assertSame(201, $create->status, $create->body());
        $sessionId = (string) $create->data['session']['id'];

        // Turn the global profile on after the session exists.
        $this->post('/api/v1/admin/probability/global', [
            'enabled' => true,
            'any_win_target' => 0.5,
        ], $this->asAdmin());

        // The session's stored state is untouched: its schema and round count
        // are what they were, and the round still settles.
        $round = $this->post("/api/v1/sessions/$sessionId/actions", [
            'action' => 'spin',
            'bet' => 100,
        ], $this->asPlayer());

        self::assertSame(200, $round->status, $round->body());
        self::assertSame('spin', $round->data['round']['action']);
    }

    // ------------------------------------------------------------------- misc

    public function testJackpotConfigurationIsReportedSeparatelyFromOrdinaryWins(): void
    {
        $gameId = $this->firstSlotId();

        $this->post("/api/v1/admin/probability/games/$gameId", [
            'enabled' => true,
            'any_win_target' => 0.5,
            'jackpot_probability' => 0.001,
            'jackpot_tiers' => ['grand' => ['probability' => 0.0001, 'payout_multiplier' => 500.0]],
        ], $this->asAdmin());

        $response = $this->get('/api/v1/admin/jackpots', $this->asAdmin());

        self::assertSame(200, $response->status, $response->body());
        $profiles = $response->data['profiles'];
        self::assertNotEmpty($profiles);
        self::assertSame($gameId, $profiles[0]['game_id']);
        self::assertArrayHasKey('grand', $profiles[0]['tiers']);
    }

    public function testTheOverviewReportsCountsAndTheMetricGlossary(): void
    {
        $response = $this->get('/api/v1/admin/overview', $this->asAdmin());

        self::assertSame(200, $response->status, $response->body());
        self::assertGreaterThan(0, $response->data['counts']['games']);
        self::assertArrayHasKey('any_win_target', $response->data['metrics']);
        self::assertArrayHasKey('rtp_target', $response->data['metrics']);
        self::assertArrayHasKey('jackpot_probability', $response->data['metrics']);
    }

    /**
     * The whole jackpot path, through the real router: configure the roll, play
     * a round as a player, then read the jackpot history back as the
     * administrator. This is the seam the dashboard depends on — a configured
     * jackpot that is paid must also be visible in the history the admin page
     * renders.
     */
    public function testAConfiguredJackpotAppearsInTheAdminHistory(): void
    {
        $gameId = 'jili.fortune-gems-2';

        $configure = $this->post("/api/v1/admin/probability/games/$gameId", [
            'enabled' => true,
            'jackpot_probability' => 1.0,
            'jackpot_tiers' => ['grand' => ['probability' => 1.0, 'payout_multiplier' => 100.0]],
        ], $this->asAdmin());
        self::assertSame(200, $configure->status, $configure->body());

        $create = $this->post('/api/v1/sessions', ['game_id' => $gameId], $this->asPlayer());
        self::assertSame(201, $create->status, $create->body());
        $sessionId = (string) $create->data['session']['id'];

        $spin = $this->post("/api/v1/sessions/$sessionId/actions", [
            'action' => 'spin',
            'bet' => 100,
        ], $this->asPlayer());
        self::assertSame(200, $spin->status, $spin->body());

        $history = $this->get('/api/v1/admin/jackpots', $this->asAdmin(), ['game_id' => $gameId]);
        self::assertSame(200, $history->status, $history->body());
        self::assertNotEmpty(
            $history->data['history'],
            'A jackpot paid to a player must appear in the administrator jackpot history.'
        );
        self::assertSame('grand', $history->data['history'][0]['tier']);
    }

    // --------------------------------------------------------------- fixtures

    private function firstSlotId(): string
    {
        $ids = $this->slotIds();
        self::assertNotEmpty($ids, 'The suite needs at least one registered slot game.');

        return $ids[0];
    }

    /** @return list<string> */
    private function slotIds(): array
    {
        $this->engine->discoverGames();
        $ids = [];
        // The registry's `all()` returns metadata, not modules.
        foreach ($this->engine->registry()->all() as $metadata) {
            if ($metadata->category()->value === 'slot' && $metadata->isEnabled()) {
                $ids[] = $metadata->id();
            }
        }

        return $ids;
    }
}
