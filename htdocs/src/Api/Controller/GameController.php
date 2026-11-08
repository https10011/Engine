<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Controller;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Api\Presenter\GamePresenter;
use CasinoEngine\Api\Presenter\ThemeCatalogue;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Registry\GameRegistryInterface;

/**
 * Game discovery and metadata.
 *
 * The listing is built entirely from the registry, so a module that registers
 * itself appears here with no controller change. Metadata is public
 * information and is readable without a token, which lets a frontend render a
 * lobby before a player logs in.
 */
final class GameController extends AbstractController
{
    public function __construct(private readonly GameRegistryInterface $registry)
    {
    }

    /**
     * @param array<string, string> $parameters
     */
    public function index(array $parameters, Request $request, RequestContext $context): Response
    {
        $category = $request->query('category');
        $games = $this->registry->all();

        if (is_string($category) && $category !== '') {
            $enum = GameCategory::tryFrom($category);
            if ($enum === null) {
                throw new ValidationException("Unknown game category '$category'.");
            }
            $games = $this->registry->byCategory($enum);
        }

        $items = array_map(
            static fn (GameMetadataInterface $metadata): array => $metadata->toArray(),
            $games
        );

        return Response::ok(
            ['games' => array_values($items)],
            200,
            ['count' => count($items)]
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    public function show(array $parameters, Request $request, RequestContext $context): Response
    {
        $game = $this->registry->get($parameters['gameId'] ?? '');

        return Response::ok([
            'game'          => $game->metadata()->toArray(),
            'category'      => $game->category()->value,
            'configuration' => $game->defaultConfiguration()->toArray(),
        ]);
    }

    /**
     * The full presentation contract for one game.
     *
     * This is what a themed interface builds itself from: the theme, the grid
     * shape, the symbol set, the paytable and the feature list, all read from
     * the module. The frontend therefore never re-derives a game's mathematics
     * and cannot disagree with the engine about what a symbol pays.
     *
     * @param array<string, string> $parameters
     */
    public function presentation(array $parameters, Request $request, RequestContext $context): Response
    {
        $game = $this->registry->get($parameters['gameId'] ?? '');

        $presenter = new GamePresenter($game, $game->defaultConfiguration()->toArray());

        return Response::ok($presenter->present());
    }

    /**
     * The lobby catalogue: every enabled game, with its theme attached.
     *
     * Public, like the plain listing, so a lobby renders before a player logs
     * in. The theme travels with each card so the lobby does not need a second
     * request per game.
     *
     * @param array<string, string> $parameters
     */
    public function lobby(array $parameters, Request $request, RequestContext $context): Response
    {
        $category = $request->query('category');
        $games = $this->registry->all();

        if (is_string($category) && $category !== '') {
            $enum = GameCategory::tryFrom($category);
            if ($enum === null) {
                throw new ValidationException("Unknown game category '$category'.");
            }
            $games = $this->registry->byCategory($enum);
        }

        $cards = [];
        // The registry lists metadata; `byCategory` and `all` both return the
        // metadata list, so a disabled module is filtered here rather than in
        // the registry, which needs the full set for lookups.
        foreach ($games as $metadata) {
            if (!$metadata->isEnabled()) {
                continue;
            }

            $cards[] = [
                'game'  => $metadata->toArray(),
                'theme' => ThemeCatalogue::for($metadata),
            ];
        }

        return Response::ok(['games' => $cards], 200, ['count' => count($cards)]);
    }
}
