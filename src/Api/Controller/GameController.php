<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Controller;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Engine;
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
    public function __construct(
        private readonly Engine $engine,
        private readonly GameRegistryInterface $registry
    ) {
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
}
