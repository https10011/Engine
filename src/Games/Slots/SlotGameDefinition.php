<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots;

use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Frameworks\Slot\Evaluation\GridEvaluatorInterface;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Reel\ReelConfiguration;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolSet;

/**
 * Everything that makes one slot different from another.
 *
 * The five pieces are the same five the framework composes — reels, symbols,
 * paytable, evaluator, features — plus the two numbers that translate paytable
 * units into a bet multiple. A game is therefore a *value*, and
 * {@see AbstractSlotGame} is the single lifecycle that plays any of them.
 *
 * `features` is a callable rather than a list because a feature is built from
 * the session configuration and from the administrator's probability policy:
 * whether cascades are enabled and how large the jackpot is are both
 * per-session decisions, so the definition has to be able to see them.
 */
final class SlotGameDefinition
{
    /**
     * @param callable(SlotConfiguration, array<string, mixed>):list<FeatureInterface> $features
     * @param array{0:int,1:int} $payoutScale Exact fraction applied to every payout
     */
    public function __construct(
        private readonly GameMetadataInterface $metadata,
        private readonly ReelConfiguration $reels,
        private readonly SymbolSet $symbols,
        private readonly Paytable $paytable,
        private readonly GridEvaluatorInterface $evaluator,
        private readonly mixed $features,
        private readonly SlotConfiguration $defaultConfiguration,
        private readonly int $unitDivisor = 1,
        private readonly int $maximumFeatureIterations = 50,
        private readonly array $payoutScale = [1, 1]
    ) {
        if (!is_callable($this->features)) {
            throw new \CasinoEngine\Core\Exception\ConfigurationException(
                'A slot definition needs a feature factory callable.'
            );
        }

        if ($this->payoutScale[0] < 1 || $this->payoutScale[1] < 1) {
            throw new \CasinoEngine\Core\Exception\ConfigurationException(
                'A payout scale must be a positive fraction.'
            );
        }
    }

    /**
     * The exact fraction that tunes the game's measured return to its published
     * one.
     *
     * A paytable is authored in round, readable units and its return is then
     * calibrated to the published figure. Applying the calibration here, to the
     * payout multiplier, keeps it exact: scaling the integer paytable instead
     * would round every entry and lose the calibration in the rounding.
     *
     * @return array{0:int,1:int}
     */
    public function payoutScale(): array
    {
        return $this->payoutScale;
    }

    public function metadata(): GameMetadataInterface
    {
        return $this->metadata;
    }

    public function reels(): ReelConfiguration
    {
        return $this->reels;
    }

    public function symbols(): SymbolSet
    {
        return $this->symbols;
    }

    public function paytable(): Paytable
    {
        return $this->paytable;
    }

    public function evaluator(): GridEvaluatorInterface
    {
        return $this->evaluator;
    }

    /**
     * @param array<string, mixed> $spinConfiguration
     * @return list<FeatureInterface>
     */
    public function features(SlotConfiguration $configuration, array $spinConfiguration = []): array
    {
        $factory = $this->features;

        /** @var list<FeatureInterface> $features */
        $features = $factory($configuration, $spinConfiguration);

        return $features;
    }

    public function defaultConfiguration(): SlotConfiguration
    {
        return $this->defaultConfiguration;
    }

    public function unitDivisor(): int
    {
        return max(1, $this->unitDivisor);
    }

    public function maximumFeatureIterations(): int
    {
        return max(1, $this->maximumFeatureIterations);
    }
}
