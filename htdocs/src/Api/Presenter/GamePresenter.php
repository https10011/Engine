<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Presenter;

use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Reel\ReelConfiguration;
use CasinoEngine\Frameworks\Slot\Symbol\Symbol;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolSet;
use CasinoEngine\Games\Slots\AbstractSlotGame;

/**
 * Turns a game module into the description a frontend needs to draw it.
 *
 * The frontend must never re-derive a game's mathematics. It receives the
 * symbol set, the paytable and the grid shape from here — read from the same
 * `SlotGameDefinition` the engine spins — so a themed interface cannot drift
 * from the game it presents. If a paytable entry changes in the module, the
 * rendered paytable changes with it and no frontend edit is required.
 *
 * Non-slot games get a generic description. Their presentation is driven by
 * the round result's own state (a hand, a wheel), which is already structured
 * and needs no extra catalogue.
 */
final class GamePresenter
{
    /** @param array<string, mixed> $configuration */
    public function __construct(
        private readonly GameInterface $game,
        private readonly array $configuration
    ) {
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        $metadata = $this->game->metadata();

        $presentation = [
            'game'          => $metadata->toArray(),
            'category'      => $this->game->category()->value,
            'configuration' => $this->configuration,
            'layout'        => $this->layout(),
            'theme'         => ThemeCatalogue::for($metadata),
        ];

        if ($this->game instanceof AbstractSlotGame) {
            $presentation['slot'] = $this->slotPresentation($this->game);
        }

        return $presentation;
    }

    /**
     * The grid shape, so a themed interface knows how many reels and rows to
     * draw before it ever spins.
     *
     * @return array<string, mixed>
     */
    private function layout(): array
    {
        $layout = [
            'category' => $this->game->category()->value,
            'id'       => $this->game->metadata()->id(),
        ];

        if ($this->game instanceof AbstractSlotGame) {
            $reels = $this->game->slotDefinition()->reels();
            $layout['reels']       = $reels->reelCount();
            $layout['rows']        = $reels->rows();
            $layout['rectangular'] = $reels->isRectangular();
        }

        return $layout;
    }

    /**
     * The symbol set, the paytable and the feature list — everything a themed
     * interface needs to render a symbol, a paytable row and a rules panel.
     *
     * @return array<string, mixed>
     */
    private function slotPresentation(AbstractSlotGame $game): array
    {
        $definition = $game->slotDefinition();

        return [
            'symbols'      => $this->symbols($definition->symbols()),
            'paytable'     => $this->paytable($definition->paytable()),
            'reels'        => $this->reels($definition->reels()),
            'features'     => $this->featureNames($game),
            'unit_divisor' => $definition->unitDivisor(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function symbols(SymbolSet $set): array
    {
        return array_map(
            static fn (Symbol $symbol): array => [
                'id'             => $symbol->id,
                'name'           => $symbol->name,
                'type'           => $symbol->type->value,
                'is_wild'        => $symbol->isWild(),
                'is_scatter'     => $symbol->isScatter(),
                'win_multiplier' => $symbol->winMultiplier,
            ],
            $set->all()
        );
    }

    /** @return array<string, mixed> */
    private function paytable(Paytable $paytable): array
    {
        $serialised = $paytable->toArray();

        return [
            'line_payouts'    => $serialised['line_payouts'],
            'scatter_payouts' => $serialised['scatter_payouts'],
            'symbols'         => $paytable->symbolIds(),
        ];
    }

    /**
     * The reel strips themselves.
     *
     * These are the engine's own generated strips rather than a provider's
     * proprietary ones, which is stated plainly in the game's specification.
     * Sending them lets the interface show a real reel with real symbols
     * instead of inventing a decorative one.
     *
     * @return array<string, mixed>
     */
    private function reels(ReelConfiguration $reels): array
    {
        $strips = [];
        foreach ($reels->reels() as $reel) {
            $strips[] = $reel->symbols();
        }

        return [
            'count'  => $reels->reelCount(),
            'rows'   => $reels->rows(),
            'strips' => $strips,
        ];
    }

    /**
     * Feature type names, read from the game's own feature factory.
     *
     * The factory takes the session configuration; the game's own default is
     * passed, so the list describes what the title can do rather than what one
     * particular session happens to enable.
     *
     * @return list<string>
     */
    private function featureNames(AbstractSlotGame $game): array
    {
        $definition = $game->slotDefinition();
        $names = [];

        foreach ($definition->features($definition->defaultConfiguration()) as $feature) {
            $names[] = $feature->type();
        }

        return array_values(array_unique($names));
    }
}
