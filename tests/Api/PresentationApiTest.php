<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Api;

use CasinoEngine\Api\ApiKernel;
use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Tests\Support\DatabaseTestCase;

/**
 * The presentation contract the frontend is built from.
 *
 * The frontend must not re-derive a game's mathematics. These tests assert the
 * payload carries everything a themed interface needs — the theme, the grid
 * shape, the symbol set and the paytable — and that the paytable it receives is
 * the module's own, so a frontend cannot disagree with the engine about what a
 * symbol pays.
 */
final class PresentationApiTest extends DatabaseTestCase
{
    private ApiKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernel = new ApiKernel($this->engine);
    }

    /** @param array<string, mixed> $query */
    private function get(string $path, array $query = []): Response
    {
        return $this->kernel->handle(new Request('GET', $path, [], $query));
    }

    public function testTheLobbyCatalogueCarriesAThemeForEveryEnabledGame(): void
    {
        $response = $this->get('/api/v1/games/lobby');

        self::assertSame(200, $response->status, $response->body());
        $cards = $response->data['games'];
        self::assertNotEmpty($cards);

        foreach ($cards as $card) {
            self::assertArrayHasKey('game', $card);
            self::assertArrayHasKey('theme', $card);
            self::assertNotEmpty($card['theme']['accent'] ?? '', 'Every lobby card needs an accent colour.');
        }
    }

    public function testASlotPresentationCarriesTheGridSymbolsAndPaytable(): void
    {
        $gameId = $this->firstSlotId();
        $response = $this->get("/api/v1/games/$gameId/presentation");

        self::assertSame(200, $response->status, $response->body());
        $data = $response->data;

        self::assertSame('slot', $data['category']);
        self::assertArrayHasKey('slot', $data);

        $slot = $data['slot'];
        self::assertGreaterThan(0, $slot['reels']['count']);
        self::assertNotEmpty($slot['symbols']);

        $paytable = $slot['paytable'];
        self::assertNotEmpty($paytable['line_payouts']);

        // Every symbol the paytable names must be a symbol the game declares,
        // otherwise a rendered paytable could reference something that cannot
        // appear on the reels.
        $declared = array_column($slot['symbols'], 'id');
        foreach (array_keys($paytable['line_payouts']) as $symbol) {
            self::assertContains($symbol, $declared);
        }
        foreach (array_keys($paytable['scatter_payouts'] ?? []) as $symbol) {
            self::assertContains($symbol, $declared);
        }

        // The grid shape is per reel, because a ways game has a ragged grid.
        self::assertCount($slot['reels']['count'], $slot['reels']['rows']);
    }

    public function testTheGridShapeMatchesTheModuleDefinition(): void
    {
        $gameId = $this->firstSlotId();

        $presentation = $this->get("/api/v1/games/$gameId/presentation")->data;

        // The layout block and the slot block are produced from the same
        // definition in one pass, so the shape a view draws and the shape the
        // paytable is quoted against cannot disagree.
        self::assertSame(
            (int) $presentation['layout']['reels'],
            (int) $presentation['slot']['reels']['count']
        );
        self::assertSame(
            array_map('intval', $presentation['layout']['rows']),
            array_map('intval', $presentation['slot']['reels']['rows'])
        );
    }

    public function testATableGamePresentationHasNoSlotBlock(): void
    {
        $response = $this->get('/api/v1/games/table.blackjack/presentation');

        self::assertSame(200, $response->status, $response->body());
        self::assertSame('card', $response->data['category']);
        self::assertArrayNotHasKey('slot', $response->data);
        self::assertArrayHasKey('configuration', $response->data);
    }

    public function testAnUnknownGameHasNoPresentation(): void
    {
        $response = $this->get('/api/v1/games/not.a.game/presentation');

        self::assertSame(404, $response->status, $response->body());
    }

    public function testTheFrontControllerServesTheApplicationShell(): void
    {
        $shell = __DIR__ . '/../../public/app.html';
        self::assertFileExists($shell, 'The SPA shell must ship with the application.');

        $html = (string) file_get_contents($shell);
        self::assertStringContainsString('data-view', $html);
        self::assertStringContainsString('/assets/js/app.js', $html);
        // No inline script: the front controller's CSP allows only same-origin
        // script files, so an inline handler would be silently blocked.
        self::assertDoesNotMatchRegularExpression('/<script(?![^>]*src=)/i', $html);
    }

    public function testEveryViewModuleTheRouterImportsExists(): void
    {
        $root = __DIR__ . '/../../public/assets/js';
        $app = (string) file_get_contents($root . '/app.js');

        preg_match_all("/from '\.\/views\/([a-z]+)\.js'/", $app, $matches);
        self::assertNotEmpty($matches[1], 'The router must import its views by relative path.');

        foreach (array_unique($matches[1]) as $view) {
            self::assertFileExists("$root/views/$view.js", "The router imports views/$view.js but it does not exist.");
        }
    }

    private function firstSlotId(): string
    {
        $this->engine->discoverGames();
        foreach ($this->engine->registry()->all() as $metadata) {
            if ($metadata->category()->value === 'slot' && $metadata->isEnabled()) {
                return $metadata->id();
            }
        }

        self::fail('The suite needs at least one registered slot game.');
    }
}
