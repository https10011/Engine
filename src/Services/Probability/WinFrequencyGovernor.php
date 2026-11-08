<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Probability;

use CasinoEngine\Core\Contract\Service\RngServiceInterface;

/**
 * Steers a round towards a configured any-win / net-positive target without
 * ever fabricating an outcome.
 *
 * A target is a *rate*, so the governor first decides — with its own
 * independent draw against the configured probability — which class the round
 * should be, and then accepts the first genuine outcome whose class matches:
 *
 *     want = draw from {net_positive: n, any_win_only: a - n, loss: 1 - a}
 *     outcome = draw a genuine outcome
 *     while attempts < bound and class(outcome) != want:
 *         outcome = draw again
 *     accept the outcome
 *
 * Three consequences follow, and each is deliberate:
 *
 *  1. Every accepted outcome is a real draw from the game's own reels. The
 *     governor chooses *how many* genuine draws to take; it never edits a grid,
 *     a symbol or a payout.
 *  2. The observed class rate converges on the configured probability. Choosing
 *     the class first is what makes the target a *rate*. A loop that instead
 *     accepted the first satisfying draw saturated near 100%: over a bounded
 *     number of attempts a satisfying draw is almost certain, so a 30%, 50% and
 *     90% target all produced the same ~100% outcome.
 *  3. The RNG stays independent: the class draw is independent of the outcome
 *     draws, so a 50% target yields a random half of winners, never a forced
 *     alternation.
 *
 * The bound is finite, so an unreachable class cannot hang a request. When the
 * bound is exhausted the last genuine outcome is returned and the shortfall is
 * reported, so the dashboard can say "target 95%, observed 47%, unreachable
 * under this game's rules" instead of claiming compliance.
 */
final class WinFrequencyGovernor
{
    public const CLASS_NET_POSITIVE = 'net_positive';
    public const CLASS_ANY_WIN = 'any_win';
    public const CLASS_LOSS = 'loss';

    /**
     * @param callable():mixed $draw Produces one genuine outcome.
     * @param callable(mixed):OutcomeClassification $classify Describes it.
     * @return array{outcome:mixed,attempts:int,target_met:bool,target:?string,desired:?string}
     */
    public function steer(
        ProbabilityProfile $profile,
        RngServiceInterface $rng,
        callable $draw,
        callable $classify
    ): array {
        $targets = $this->targets($profile);
        if ($targets === []) {
            return [
                'outcome'    => $draw(),
                'attempts'   => 1,
                'target_met' => true,
                'target'     => null,
                'desired'    => null,
            ];
        }

        // Once a net-positive target is set, the two win classes have to be told
        // apart, so an "any-win" round means a win that is *not* net-positive.
        $strictAnyWin = $profile->netPositiveTarget !== null;
        $label = $strictAnyWin ? self::CLASS_NET_POSITIVE : self::CLASS_ANY_WIN;

        $desired = $this->desiredClass($rng, $targets);

        $outcome = $draw();
        $attempts = 1;
        if ($this->matches($classify($outcome), $desired, $strictAnyWin)) {
            return [
                'outcome'    => $outcome,
                'attempts'   => 1,
                'target_met' => true,
                'target'     => $label,
                'desired'    => $desired,
            ];
        }

        $bound = max(1, $profile->maxResampleAttempts);
        while ($attempts < $bound) {
            $outcome = $draw();
            $attempts++;

            if ($this->matches($classify($outcome), $desired, $strictAnyWin)) {
                return [
                    'outcome'    => $outcome,
                    'attempts'   => $attempts,
                    'target_met' => true,
                    'target'     => $label,
                    'desired'    => $desired,
                ];
            }
        }

        // The bound was exhausted without reaching the desired class. The last
        // genuine outcome is returned; the shortfall is reported rather than
        // hidden by faking a result.
        return [
            'outcome'    => $outcome,
            'attempts'   => $attempts,
            'target_met' => false,
            'target'     => $label,
            'desired'    => $desired,
        ];
    }

    /**
     * The configured class probabilities.
     *
     * A net-positive round is by definition also an any-win round, so the
     * any-win target is split into a net-positive part and a non-net-positive
     * part. An any-win target below the net-positive one is contradictory;
     * raising it keeps the engine consistent rather than silently satisfying
     * neither.
     *
     * @return array<string, float>
     */
    private function targets(ProbabilityProfile $profile): array
    {
        if (!$profile->enabled) {
            return [];
        }

        $anyWin = $profile->anyWinTarget;
        $netPositive = $profile->netPositiveTarget;

        if ($anyWin === null && $netPositive === null) {
            return [];
        }

        if ($anyWin !== null && $netPositive !== null && $netPositive > $anyWin) {
            $anyWin = $netPositive;
        }

        if ($anyWin === null) {
            return [
                self::CLASS_NET_POSITIVE => (float) $netPositive,
                self::CLASS_ANY_WIN      => 0.0,
                self::CLASS_LOSS         => max(0.0, 1.0 - (float) $netPositive),
            ];
        }

        return [
            self::CLASS_NET_POSITIVE => (float) ($netPositive ?? 0.0),
            self::CLASS_ANY_WIN      => max(0.0, $anyWin - (float) ($netPositive ?? 0.0)),
            self::CLASS_LOSS         => max(0.0, 1.0 - $anyWin),
        ];
    }

    /**
     * Draws the class this round should be, from the configured probabilities.
     *
     * @param array<string, float> $targets
     */
    private function desiredClass(RngServiceInterface $rng, array $targets): string
    {
        $roll = $rng->float();
        $cumulative = 0.0;

        foreach ($targets as $class => $probability) {
            $cumulative += $probability;
            if ($roll < $cumulative) {
                return $class;
            }
        }

        return self::CLASS_LOSS;
    }

    private function matches(OutcomeClassification $classification, string $desired, bool $strictAnyWin): bool
    {
        return match ($desired) {
            self::CLASS_NET_POSITIVE => $classification->netPositive,
            self::CLASS_ANY_WIN      => $classification->anyWin
                && (!$strictAnyWin || !$classification->netPositive),
            self::CLASS_LOSS         => !$classification->anyWin,
            default                  => true,
        };
    }
}
