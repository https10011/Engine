<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Synthetic;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Contract\GameActionInterface;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Contract\GameOutcomeInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameMetadata;
use CasinoEngine\Core\Value\GameOutcome;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;
use CasinoEngine\Frameworks\Slot\Evaluation\PaylineEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\ComboMultiplierFeature;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Reel\ReelConfiguration;
use CasinoEngine\Frameworks\Slot\Reel\ReelStrip;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Frameworks\Slot\SlotEngine;
use CasinoEngine\Frameworks\Slot\SlotState;
use CasinoEngine\Frameworks\Slot\Symbol\Symbol;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolSet;

/**
 * Synthetic 3x3, 5-line slot.
 *
 * A test fixture, not a commercial clone. It is deliberately built from the
 * framework's real components — real reel strips, a real paytable, the real
 * payline evaluator and the real feature implementations — so that when it
 * plays, it exercises the framework end to end rather than a shortcut.
 *
 * The mechanic set is small but representative of the target titles: five
 * paylines with a wild, a scatter that triggers free spins, and an optional
 * cascade chain. A game with more elaborate mechanics is a matter of supplying
 * different components, which is the point of the framework.
 */
final class SyntheticSlotGame implements GameInterface
{
    public const ID = 'synthetic.slot3x3';

    private const SYMBOLS = [
        'A' => ['name' => 'Ace', 'weight' => 2, 'payouts' => [3 => 100]],
        'K' => ['name' => 'King', 'weight' => 3, 'payouts' => [3 => 50]],
        'Q' => ['name' => 'Queen', 'weight' => 4, 'payouts' => [3 => 25]],
        'J' => ['name' => 'Jack', 'weight' => 5, 'payouts' => [3 => 10]],
        'W' => ['name' => 'Wild', 'weight' => 1, 'payouts' => [3 => 200]],
    ];

    /** The five standard 3x3 paylines, as row indices per reel. */
    private const PAYLINES = [
        [1, 1, 1],
        [0, 0, 0],
        [2, 2, 2],
        [0, 1, 2],
        [2, 1, 0],
    ];

    /**
     * Fixed reel strips, one per reel.
     *
     * A real reel strip is a constant of the game's mathematics: it is what
     * makes the symbol distribution, and therefore the payout distribution,
     * stable across every spin. Building it from weights with a shuffle on
     * every spin would re-roll the game's mathematics each time, so the strips
     * are authored explicitly here. Their symbol counts match the weights
     * declared in {@see self::SYMBOLS} plus the wild and scatter.
     */
    private const REEL_STRIPS = [
        ['J', 'J', 'Q', 'K', 'J', 'A', 'Q', 'J', 'K', 'Q', 'W', 'J', 'S', 'Q', 'K', 'A'],
        ['K', 'Q', 'J', 'J', 'A', 'Q', 'S', 'J', 'K', 'Q', 'W', 'J', 'Q', 'A', 'K', 'J'],
        ['Q', 'J', 'K', 'A', 'J', 'Q', 'J', 'S', 'K', 'W', 'A', 'Q', 'J', 'K', 'Q', 'J'],
    ];

    public function metadata(): GameMetadataInterface
    {
        $currency = 'PHP';

        return new GameMetadata(
            self::ID,
            'Synthetic Slot 3x3',
            GameCategory::Slot,
            '1.0.0',
            '1.0.0',
            'Engine Test Fixtures',
            Money::of(100, $currency),
            Money::of(50000, $currency),
            Money::of(100, $currency),
            Volatility::Medium,
            200,
            0.95,
            true,
            ['synthetic', 'fixture', '3x3', '5-lines']
        );
    }

    public function category(): GameCategory
    {
        return GameCategory::Slot;
    }

    public function defaultConfiguration(): GameConfigurationInterface
    {
        return new SlotConfiguration(
            coinValueMinor: 100,
            betLevels: [1, 2, 5, 10],
            cascadesEnabled: true,
            maxCascades: 10,
            comboMultiplierEnabled: true,
            comboLadder: [1, 2, 3, 5],
            freeSpinsEnabled: true,
            freeSpinAwards: [3 => 10, 4 => 15, 5 => 20],
            freeSpinsRetrigger: true,
            freeSpinsCap: 100,
            scatterSymbolId: 'S'
        );
    }

    public function validateConfiguration(GameConfigurationInterface $configuration): void
    {
        if (!$configuration instanceof SlotConfiguration) {
            throw new \CasinoEngine\Core\Exception\ConfigurationException(
                'SyntheticSlotGame expects a SlotConfiguration.'
            );
        }

        $configuration->validate();
    }

    public function createInitialState(GameContext $context): GameStateInterface
    {
        return SlotState::initial();
    }

    public function hydrateState(array $data): GameStateInterface
    {
        return SlotState::fromArray($data);
    }

    public function validateAction(GameActionInterface $action, GameStateInterface $state): void
    {
        if ($action->name() !== 'spin') {
            throw new GameRuleException("Unknown action '{$action->name()}'.");
        }

        if (!$state instanceof SlotState) {
            throw new GameRuleException('The session state is not a slot state.');
        }

        // A paid spin needs a bet; a spin taken during free spins must not
        // carry one, because the engine would debit it.
        if ($state->isInFreeSpins()) {
            if ($action->bet() !== null && !$action->bet()->isZero()) {
                throw new GameRuleException('Free spins are not wagered.');
            }

            return;
        }

        if ($action->bet() === null) {
            throw new GameRuleException('A spin requires a bet.');
        }
    }

    public function execute(
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $context
    ): GameOutcomeInterface {
        if (!$state instanceof SlotState) {
            throw new GameRuleException('The session state is not a slot state.');
        }

        $configuration = $context->config();
        if (!$configuration instanceof SlotConfiguration) {
            throw new GameRuleException('The game configuration is invalid.');
        }

        $engine = $this->buildEngine($configuration, $context);

        $forced = $this->forcedPositions($action);

        $spin = $engine->spin(
            $state,
            $context->rng(),
            $configuration->toArray(),
            $forced
        );

        $wagerMultiplier = $spin->isFreeSpin ? Ratio::zero() : Ratio::one();

        return new GameOutcome(
            $spin->nextState,
            $spin->detail(),
            $spin->payoutMultiplier,
            $wagerMultiplier,
            $spin->transitions,
            $this->eventsFor($spin->transitions, $spin->isFreeSpin)
        );
    }

    /**
     * Builds the engine from the configuration.
     *
     * Built per call rather than cached because the configuration is per
     * session and the construction is cheap; caching would risk serving one
     * session's configuration to another.
     */
    private function buildEngine(SlotConfiguration $configuration, GameContext $context): SlotEngine
    {
        $symbols = $this->buildSymbols();
        $reels = $this->buildReels($symbols);

        $features = [];

        if ($configuration->cascadesEnabled) {
            $features[] = new CascadeFeature($configuration->maxCascades);
        }

        if ($configuration->comboMultiplierEnabled) {
            $features[] = new ComboMultiplierFeature($configuration->comboLadder);
        }

        if ($configuration->freeSpinsEnabled) {
            $features[] = new FreeSpinsFeature(
                $configuration->freeSpinAwards,
                $configuration->freeSpinsRetrigger,
                $configuration->freeSpinsCap,
                $configuration->scatterSymbolId
            );
        }

        return new SlotEngine(
            $reels,
            $symbols,
            $this->buildPaytable(),
            new PaylineEvaluator(self::PAYLINES),
            $features,
            // Five paylines: one paytable unit is a fifth of the total bet, so
            // a line paying 100 units pays 20x the bet.
            count(self::PAYLINES)
        );
    }

    private function buildSymbols(): SymbolSet
    {
        $symbols = [];

        foreach (self::SYMBOLS as $id => $definition) {
            if ($id === 'W') {
                continue; // added as a wild below
            }
            $symbols[] = Symbol::regular($id, $definition['name'], $definition['weight']);
        }

        $symbols[] = Symbol::wild('W', 'Wild', 1);
        $symbols[] = Symbol::scatter('S', 'Scatter', 1);

        return new SymbolSet($symbols);
    }

    private function buildReels(SymbolSet $symbols): ReelConfiguration
    {
        $reels = [];
        foreach (self::REEL_STRIPS as $strip) {
            $reels[] = ReelStrip::fromSymbols($strip);
        }

        return new ReelConfiguration($reels, 3);
    }

    private function buildPaytable(): Paytable
    {
        $payouts = [];
        foreach (self::SYMBOLS as $id => $definition) {
            $payouts[$id] = $definition['payouts'];
        }

        // The wild pays its own run of three.
        $payouts['W'] = [3 => 200];

        return new Paytable(
            $payouts,
            ['S' => [3 => 50, 4 => 100, 5 => 200]]
        );
    }

    /**
     * Test hook: an action may pin the reel stop positions.
     *
     * @return list<int>|null
     */
    private function forcedPositions(GameActionInterface $action): ?array
    {
        $positions = $action->payload()['positions'] ?? null;
        if (!is_array($positions)) {
            return null;
        }

        /** @var list<int> $positions */
        return array_values(array_map('intval', $positions));
    }

    /**
     * @param list<FeatureTransition> $transitions
     * @return list<array{name:string,payload:array<string,mixed>}>
     */
    private function eventsFor(array $transitions, bool $isFreeSpin): array
    {
        $events = [];

        foreach ($transitions as $transition) {
            if ($transition->type === 'free_spins' && $transition->status === FeatureTransition::STARTED) {
                $events[] = [
                    'name'    => 'game.free_spins.started',
                    'payload' => ['spins' => $transition->remaining],
                ];
            }
            if ($transition->type === 'cascade') {
                $events[] = [
                    'name'    => 'game.cascade',
                    'payload' => $transition->detail,
                ];
            }
        }

        return $events;
    }
}
