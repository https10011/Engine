<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Api;

use CasinoEngine\Api\ApiKernel;
use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Exception\AuthenticationException;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Services\Auth\AuthService;
use CasinoEngine\Tests\Support\DatabaseTestCase;

/**
 * The HTTP API, exercised through the real router.
 *
 * No HTTP mock and no sockets: a {@see Request} is built directly and handed
 * to the kernel, so the test asserts on the response object the server would
 * have produced. The database is real, which means the auth boundary, the
 * ownership check and the idempotency replay are all tested against the same
 * code path production uses.
 */
final class ApiTest extends DatabaseTestCase
{
    private ApiKernel $kernel;
    private int $userId;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernel = new ApiKernel($this->engine);
        $this->userId = $this->createUserWithPassword('apiuser', 'correct-horse-battery-staple');
        $this->token = $this->login('apiuser', 'correct-horse-battery-staple');
    }

    // ---------------------------------------------------------------- helpers

    private function createUserWithPassword(string $username, string $password): int
    {
        $users = $this->engine->container()->get(UserRepository::class);
        $now = $this->clock()->now()->format('Y-m-d H:i:s');
        $userId = $users->create($username, ucfirst($username), $now);

        $auth = new AuthService(
            $users,
            $this->engine->container()->get(\CasinoEngine\Persistence\Repository\TokenRepository::class),
            $this->engine->container()->get(ClockInterface::class)
        );
        $users->setCredentials($userId, $auth->hashPassword($password), PASSWORD_ARGON2ID, $now);

        $connection = $this->engine->container()->get(\CasinoEngine\Core\Database\ConnectionInterface::class);
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

    /** @param array<string, mixed> $body */
    private function post(string $path, array $body = [], array $headers = []): Response
    {
        return $this->kernel->handle(new Request('POST', $path, $headers, [], $body));
    }

    private function get(string $path, array $headers = [], array $query = []): Response
    {
        return $this->kernel->handle(new Request('GET', $path, $headers, $query));
    }

    /** @return array<string, string> */
    private function auth(): array
    {
        return ['authorization' => 'Bearer ' . $this->token];
    }

    private function startSlotSession(): string
    {
        $response = $this->post('/api/v1/sessions', ['game_id' => 'synthetic.slot3x3'], $this->auth());
        self::assertSame(201, $response->status, $response->body());

        return (string) $response->data['session']['id'];
    }

    // ------------------------------------------------------------------ tests

    public function testHealthIsPublicAndShaped(): void
    {
        $response = $this->get('/api/v1/health');

        self::assertSame(200, $response->status);
        self::assertTrue($response->envelope()['ok']);
        self::assertSame('ok', $response->data['status']);
    }

    public function testGameListingIsPublicAndBuiltFromTheRegistry(): void
    {
        $response = $this->get('/api/v1/games');

        self::assertSame(200, $response->status);
        $ids = array_column($response->data['games'], 'id');

        self::assertContains('synthetic.slot3x3', $ids);
        self::assertContains('synthetic.blackjack', $ids);
        self::assertContains('synthetic.roulette', $ids);
        self::assertSame(count($ids), $response->meta['count']);
    }

    public function testUnknownCategoryIsRejected(): void
    {
        $response = $this->get('/api/v1/games', [], ['category' => 'not-a-category']);

        self::assertSame(422, $response->status);
        self::assertSame('VALIDATION_ERROR', $response->error['code']);
    }

    public function testLoginFailureSaysNeitherWhichFieldWasWrongNorWhetherTheUserExists(): void
    {
        $response = $this->post('/api/v1/auth/login', ['username' => 'apiuser', 'password' => 'wrong']);

        self::assertSame(401, $response->status);
        self::assertSame('UNAUTHENTICATED', $response->error['code']);
        // The message names both fields together, so it never reveals which
        // half of the credential was the problem, and never confirms whether
        // the account exists at all.
        self::assertSame('Invalid username or password.', $response->error['message']);
    }

    public function testLoginRejectsAnUnknownUserWithTheSameMessageAsAWrongPassword(): void
    {
        $unknown = $this->post('/api/v1/auth/login', ['username' => 'ghost', 'password' => 'whatever']);
        $wrong = $this->post('/api/v1/auth/login', ['username' => 'apiuser', 'password' => 'wrong']);

        self::assertSame($unknown->error['message'], $wrong->error['message']);
    }

    public function testProtectedEndpointRequiresAToken(): void
    {
        $response = $this->get('/api/v1/wallet');

        self::assertSame(401, $response->status);
        self::assertSame('UNAUTHENTICATED', $response->error['code']);
    }

    public function testGarbageTokenIsRejected(): void
    {
        $response = $this->get('/api/v1/wallet', ['authorization' => 'Bearer deadbeef']);

        self::assertSame(401, $response->status);
    }

    public function testMeReturnsTheAuthenticatedUserAndBalance(): void
    {
        $response = $this->get('/api/v1/auth/me', $this->auth());

        self::assertSame(200, $response->status);
        self::assertSame($this->userId, $response->data['user']['id']);
        self::assertSame(100000, $response->data['balance']['amount']);
    }

    public function testSessionCreationAndRetrieval(): void
    {
        $sessionId = $this->startSlotSession();

        $response = $this->get('/api/v1/sessions/' . $sessionId, $this->auth());
        self::assertSame(200, $response->status);
        self::assertSame($sessionId, $response->data['session']['id']);
        self::assertSame('active', $response->data['session']['status']);
    }

    public function testPlayingASpinSettlesTheWalletAndReturnsAResult(): void
    {
        $sessionId = $this->startSlotSession();

        $response = $this->post(
            '/api/v1/sessions/' . $sessionId . '/actions',
            ['action' => 'spin', 'bet' => 100],
            $this->auth()
        );

        self::assertSame(200, $response->status, $response->body());
        $round = $response->data['round'];
        self::assertSame('spin', $round['action']);
        self::assertSame(100, $round['bet']['amount']);
        self::assertFalse($round['replayed']);

        // The engine moved exactly the bet out and the payout back in.
        $expected = 100000 - 100 + (int) $round['payout']['amount'];
        self::assertSame($expected, (int) $round['balance_after']['amount']);
    }

    public function testAnIdempotencyKeyReplaysTheOriginalRoundWithoutWageringTwice(): void
    {
        $sessionId = $this->startSlotSession();
        $path = '/api/v1/sessions/' . $sessionId . '/actions';
        $headers = $this->auth() + ['idempotency-key' => 'api-key-1'];
        $body = ['action' => 'spin', 'bet' => 100];

        $first = $this->post($path, $body, $headers);
        $second = $this->post($path, $body, $headers);

        self::assertSame($first->data['round']['round_id'], $second->data['round']['round_id']);
        self::assertFalse($first->data['round']['replayed']);
        self::assertTrue($second->data['round']['replayed']);

        $balance = $this->get('/api/v1/wallet', $this->auth());
        self::assertSame(
            (int) $first->data['round']['balance_after']['amount'],
            (int) $balance->data['balance']['amount'],
            'A replayed request must not move the balance again.'
        );
    }

    public function testReusingAKeyWithADifferentBodyIsAConflict(): void
    {
        $sessionId = $this->startSlotSession();
        $path = '/api/v1/sessions/' . $sessionId . '/actions';
        $headers = $this->auth() + ['idempotency-key' => 'api-key-2'];

        $this->post($path, ['action' => 'spin', 'bet' => 100], $headers);
        $conflict = $this->post($path, ['action' => 'spin', 'bet' => 200], $headers);

        self::assertSame(409, $conflict->status);
        self::assertSame('IDEMPOTENCY_CONFLICT', $conflict->error['code']);
    }

    public function testAnotherUserCannotReadOrPlaySomeoneElsesSession(): void
    {
        $sessionId = $this->startSlotSession();
        $this->createUserWithPassword('intruder', 'another-long-password');
        $intruderToken = $this->login('intruder', 'another-long-password');
        $intruder = ['authorization' => 'Bearer ' . $intruderToken];

        $read = $this->get('/api/v1/sessions/' . $sessionId, $intruder);
        self::assertSame(403, $read->status);
        self::assertSame('FORBIDDEN', $read->error['code']);

        $play = $this->post(
            '/api/v1/sessions/' . $sessionId . '/actions',
            ['action' => 'spin', 'bet' => 100],
            $intruder
        );
        self::assertSame(403, $play->status);
    }

    public function testAnUnknownActionIsRejectedWithoutTouchingTheWallet(): void
    {
        $sessionId = $this->startSlotSession();
        $before = (int) $this->get('/api/v1/wallet', $this->auth())->data['balance']['amount'];

        $response = $this->post(
            '/api/v1/sessions/' . $sessionId . '/actions',
            ['action' => 'teleport'],
            $this->auth()
        );

        self::assertSame(422, $response->status);
        $after = (int) $this->get('/api/v1/wallet', $this->auth())->data['balance']['amount'];
        self::assertSame($before, $after);
    }

    public function testABetAboveTheGameMaximumIsRejected(): void
    {
        $sessionId = $this->startSlotSession();

        $response = $this->post(
            '/api/v1/sessions/' . $sessionId . '/actions',
            ['action' => 'spin', 'bet' => 999999999],
            $this->auth()
        );

        self::assertSame(422, $response->status);
        self::assertSame('INVALID_BET', $response->error['code']);
    }

    public function testANegativeBetIsRejectedByValidationNotByTheEngine(): void
    {
        $sessionId = $this->startSlotSession();

        $response = $this->post(
            '/api/v1/sessions/' . $sessionId . '/actions',
            ['action' => 'spin', 'bet' => -100],
            $this->auth()
        );

        self::assertSame(422, $response->status);
        self::assertSame('VALIDATION_ERROR', $response->error['code']);
    }

    public function testAMissingRequiredFieldIsAValidationError(): void
    {
        $response = $this->post('/api/v1/sessions', [], $this->auth());

        self::assertSame(422, $response->status);
        self::assertSame('VALIDATION_ERROR', $response->error['code']);
    }

    public function testAnUnknownGameIsNotFound(): void
    {
        $response = $this->post('/api/v1/sessions', ['game_id' => 'no.such.game'], $this->auth());

        self::assertSame(404, $response->status);
        self::assertSame('GAME_NOT_FOUND', $response->error['code']);
    }

    public function testUnknownPathIs404AndWrongMethodIs405(): void
    {
        self::assertSame(404, $this->get('/api/v1/nope')->status);
        self::assertSame(405, $this->kernel->handle(new Request('DELETE', '/api/v1/games'))->status);
    }

    public function testHistoryAndStatisticsReflectPlayedRounds(): void
    {
        $sessionId = $this->startSlotSession();
        $this->post('/api/v1/sessions/' . $sessionId . '/actions', ['action' => 'spin', 'bet' => 100], $this->auth());

        $history = $this->get('/api/v1/history/rounds', $this->auth());
        self::assertSame(200, $history->status);
        self::assertSame(1, $history->data['total']);
        self::assertSame(100, $history->data['rounds'][0]['bet']);

        $statistics = $this->get('/api/v1/statistics', $this->auth());
        self::assertSame(1, $statistics->data['statistics']['rounds']);
        self::assertSame(100, $statistics->data['statistics']['wagered']);
    }

    public function testWalletTransactionsAreScopedToTheCaller(): void
    {
        $mine = $this->startSlotSession();
        $this->post('/api/v1/sessions/' . $mine . '/actions', ['action' => 'spin', 'bet' => 100], $this->auth());

        $this->createUserWithPassword('other', 'yet-another-long-password');
        $otherToken = $this->login('other', 'yet-another-long-password');
        $other = ['authorization' => 'Bearer ' . $otherToken];
        $theirs = (string) $this->post('/api/v1/sessions', ['game_id' => 'synthetic.slot3x3'], $other)->data['session']['id'];
        $this->post('/api/v1/sessions/' . $theirs . '/actions', ['action' => 'spin', 'bet' => 100], $other);

        $response = $this->get('/api/v1/wallet/transactions', $this->auth());
        self::assertSame(200, $response->status);
        self::assertNotEmpty($response->data['transactions']);

        // A transaction's reference embeds the session it came from, so if any
        // reference names the other user's session, scoping has failed.
        foreach ($response->data['transactions'] as $transaction) {
            self::assertStringContainsString($mine, (string) $transaction['reference']);
            self::assertStringNotContainsString($theirs, (string) $transaction['reference']);
        }
    }

    public function testLogoutRevokesTheToken(): void
    {
        $this->post('/api/v1/auth/logout', [], $this->auth());

        $response = $this->get('/api/v1/wallet', $this->auth());
        self::assertSame(401, $response->status);
    }

    public function testAnExpiredTokenIsRejected(): void
    {
        $this->clock()->advance(86400 * 2);

        $response = $this->get('/api/v1/wallet', $this->auth());
        self::assertSame(401, $response->status);
    }

    public function testErrorEnvelopeNeverLeaksInternals(): void
    {
        $response = $this->post('/api/v1/sessions', ['game_id' => 'no.such.game'], $this->auth());
        $message = (string) $response->error['message'];

        foreach (['SELECT', 'INSERT', 'PDO', '/workspace', '.php', 'Stack trace'] as $needle) {
            self::assertStringNotContainsString($needle, $message);
        }
    }

    public function testTheApiAndTheEngineSettleARoundIdentically(): void
    {
        // The API must not contain a second implementation of game logic. Both
        // paths are seeded identically and start from an identical balance, so
        // a divergence here would mean the controller had reimplemented
        // something the round service already does.
        $sessionId = $this->startSlotSession();
        $viaApi = $this->post(
            '/api/v1/sessions/' . $sessionId . '/actions',
            ['action' => 'spin', 'bet' => 100],
            $this->auth()
        )->data['round'];

        $userId = $this->createUserWithBalance(100000, 'direct');
        $game = $this->engine->registry()->get('synthetic.slot3x3');
        $session = $this->engine->sessions()->create(
            $userId,
            'synthetic.slot3x3',
            $game->createInitialState($this->engine->contextFor($game))
        );
        $direct = $this->engine->rounds()->execute(
            $session,
            \CasinoEngine\Core\Value\GameAction::withBet('spin', \CasinoEngine\Core\Value\Money::of(100))
        );

        self::assertSame($direct->bet->minorUnits(), (int) $viaApi['bet']['amount']);
        self::assertSame($direct->payout->minorUnits(), (int) $viaApi['payout']['amount']);
        self::assertSame(
            $direct->state->toArray(),
            $viaApi['state'],
            'The API must return the state the round service produced, not a re-derived one.'
        );
    }

    public function testRateLimitingKicksInAfterTheConfiguredBudget(): void
    {
        $connection = $this->engine->container()->get(\CasinoEngine\Core\Database\ConnectionInterface::class);
        $connection->pdo()->exec('DELETE FROM rate_limit_buckets');

        $router = (new ApiKernel($this->engine))->router();
        $router->withRateLimiting(
            $this->engine->container()->get(\CasinoEngine\Persistence\Repository\RateLimitRepository::class),
            3
        );

        $statuses = [];
        for ($i = 0; $i < 5; $i++) {
            $statuses[] = $router->dispatch(new Request('GET', '/api/v1/health'))->status;
        }

        self::assertSame([200, 200, 200, 429, 429], $statuses);

        $limited = $router->dispatch(new Request('GET', '/api/v1/health'));
        self::assertSame('RATE_LIMIT_EXCEEDED', $limited->error['code']);
    }
}
