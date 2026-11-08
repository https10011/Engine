<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Probability;

/**
 * Steers a round towards a configured any-win / net-positive target without
 * ever fabricating an outcome.
 *
 * The mechanism is bounded rejection sampling. The governor is handed a
 * `draw()` callable that produces a *genuine* outcome and a `classify()`
 * callable that describes it. It draws, checks, and either accepts or draws
 * again, up to the profile's bound:
 *
 *     draw outcome
 *     while attempts < bound and classification != target class:
 *         draw outcome again
 *     accept the last outcome drawn
 *
 * Three consequences follow, and each is deliberate:
 *
 *  1. Every accepted outcome is a real draw from the game's own reels. The
 *     governor never edits a grid, a symbol or a payout.
 *  2. The bound is finite, so an unreachable target cannot hang a request. When
 *     the bound is exhausted the governor returns the last genuine outcome and
 *     reports the shortfall, so the dashboard can say "target 95%, observed
 *     47%, unreachable under this game's rules" instead of claiming compliance.
 *  3. The RNG stream stays independent. A 50% target is a distribution goal
 *     over many rounds, not a rule that forces every second spin to win.
 */
final class WinFrequencyGovernor
{
    /**
     * @param callable():mixed $draw Produces one genuine outcome.
     * @param callable(mixed):OutcomeClassification $classify Describes it.
     * @return array{outcome:mixed,attempts:int,target_met:bool,target:?string}
     */
    public function steer(ProbabilityProfile $profile, callable $draw, callable $classify): array
    {
        $first = $draw();
        $classification = $classify($first);

        $target = $this->targetClass($profile);
        if ($target === null || $this->satisfies($classification, $target)) {
            return [
                'outcome'    => $first,
                'attempts'   => 1,
                'target_met' => $target === null || $this->satisfies($classification, $target),
                'target'     => $target,
            ];
        }

        $bound = max(1, $profile->maxResampleAttempts);
        $outcome = $first;
        $attempts = 1;

        while ($attempts < $bound) {
            $outcome = $draw();
            $attempts++;

            if ($this->satisfies($classify($outcome), $target)) {
                return [
                    'outcome'    => $outcome,
                    'attempts'   => $attempts,
                    'target_met' => true,
                    'target'     => $target,
                ];
            }
        }

        // The bound was exhausted without reaching the target class. The last
        // genuine outcome is returned; the shortfall is reported rather than
        // hidden by faking a win.
        return [
            'outcome'    => $outcome,
            'attempts'   => $attempts,
            'target_met' => false,
            'target'     => $target,
        ];
    }

    /**
     * The class the profile is asking for, or null when it asks for nothing.
     *
     * A net-positive target is expressed through the any-win governor because a
     * net-positive round is by definition also an any-win round; the game's own
     * paytable decides whether a win is net-positive, and a game whose wins are
     * all sub-1x is reported as unable to satisfy it.
     */
    private function targetClass(ProbabilityProfile $profile): ?string
    {
        if (!$profile->enabled) {
            return null;
        }

        if ($profile->netPositiveTarget !== null) {
            return 'net_positive';
        }

        if ($profile->anyWinTarget !== null) {
            return 'any_win';
        }

        return null;
    }

    private function satisfies(OutcomeClassification $classification, string $target): bool
    {
        return match ($target) {
            'net_positive' => $classification->netPositive,
            'any_win'      => $classification->anyWin,
            default        => true,
        };
    }
}
