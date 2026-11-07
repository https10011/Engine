<?php

declare(strict_types=1);

namespace CasinoEngine\Simulation;

/**
 * Streaming statistical accumulator for one simulation run.
 *
 * Uses Welford's online algorithm for the mean and variance so a run of
 * millions of rounds never needs to keep every result in memory. Everything it
 * reports is a property of the *sample it observed*; nothing here is a claim
 * about a game's theoretical mathematics.
 *
 * All money is held in minor units (integers) so a long run cannot accumulate
 * floating-point drift in the totals.
 */
final class SimulationStatistics
{
    private int $rounds = 0;

    /** Rounds on which the player staked something (paid rounds). */
    private int $paidRounds = 0;

    private int $totalWageredMinor = 0;
    private int $totalReturnedMinor = 0;

    /** Rounds that returned more than they wagered. */
    private int $winningRounds = 0;

    /** Rounds that returned exactly the stake (a push or a break-even spin). */
    private int $pushRounds = 0;

    private int $losingRounds = 0;

    /** Rounds with a payout strictly greater than zero. */
    private int $hits = 0;

    private int $maxPayoutMinor = 0;
    private int $minPayoutMinor = PHP_INT_MAX;

    private int $maxMultiplierScaled = 0;

    // Welford accumulators, keyed on the per-round return multiplier scaled by
    // 1e6 so the running mean stays an integer computation.
    private float $meanReturn = 0.0;
    private float $m2Return = 0.0;

    /** @var array<string, int> payout multiplier (scaled, 2dp) => count */
    private array $multiplierHistogram = [];

    /** @var array<string, int> feature/event name => number of rounds it fired in */
    private array $featureCounts = [];

    private int $featureRounds = 0;

    /**
     * Records one settled round.
     *
     * @param int $wageredMinor stake actually debited this round
     * @param int $returnedMinor payout credited this round
     * @param list<string> $features feature or event names that fired
     */
    public function record(int $wageredMinor, int $returnedMinor, array $features = []): void
    {
        $this->rounds++;

        if ($wageredMinor > 0) {
            $this->paidRounds++;
        }

        $this->totalWageredMinor += $wageredMinor;
        $this->totalReturnedMinor += $returnedMinor;

        if ($wageredMinor > 0) {
            if ($returnedMinor > $wageredMinor) {
                $this->winningRounds++;
            } elseif ($returnedMinor === $wageredMinor) {
                $this->pushRounds++;
            } else {
                $this->losingRounds++;
            }
        }

        if ($returnedMinor > 0) {
            $this->hits++;
        }

        $this->maxPayoutMinor = max($this->maxPayoutMinor, $returnedMinor);
        $this->minPayoutMinor = min($this->minPayoutMinor, $returnedMinor);

        // Per-round return as a fraction of the stake. Free rounds (no stake)
        // contribute nothing to the return distribution.
        if ($wageredMinor > 0) {
            $return = $returnedMinor / $wageredMinor;
            $this->meanReturn += ($return - $this->meanReturn) / $this->paidRounds;
            $this->m2Return += ($return - $this->meanReturn) * ($return - $this->meanReturn);

            $bucket = number_format($return, 2, '.', '');
            $this->multiplierHistogram[$bucket] = ($this->multiplierHistogram[$bucket] ?? 0) + 1;

            $scaled = (int) round($return * 100);
            $this->maxMultiplierScaled = max($this->maxMultiplierScaled, $scaled);
        }

        if ($features !== []) {
            $this->featureRounds++;
            foreach (array_unique($features) as $feature) {
                $this->featureCounts[$feature] = ($this->featureCounts[$feature] ?? 0) + 1;
            }
        }
    }

    public function rounds(): int
    {
        return $this->rounds;
    }

    public function paidRounds(): int
    {
        return $this->paidRounds;
    }

    public function totalWageredMinor(): int
    {
        return $this->totalWageredMinor;
    }

    public function totalReturnedMinor(): int
    {
        return $this->totalReturnedMinor;
    }

    /**
     * Observed return to player: total returned over total wagered.
     *
     * This is a measurement of the sample, not the game's theoretical RTP.
     */
    public function observedRtp(): ?float
    {
        if ($this->totalWageredMinor === 0) {
            return null;
        }

        return $this->totalReturnedMinor / $this->totalWageredMinor;
    }

    /** Observed hit frequency: rounds with a non-zero payout over all rounds. */
    public function hitFrequency(): ?float
    {
        if ($this->rounds === 0) {
            return null;
        }

        return $this->hits / $this->rounds;
    }

    /** Observed win frequency over paid rounds. */
    public function winFrequency(): ?float
    {
        if ($this->paidRounds === 0) {
            return null;
        }

        return $this->winningRounds / $this->paidRounds;
    }

    /**
     * Standard error of the observed per-round return.
     *
     * This quantifies how much the observed RTP would move if the run were
     * repeated; it is the honest way to say "the sample is this noisy". With
     * fewer than two paid rounds there is nothing to estimate.
     */
    public function standardError(): ?float
    {
        if ($this->paidRounds < 2) {
            return null;
        }

        $variance = $this->m2Return / ($this->paidRounds - 1);

        return sqrt($variance / $this->paidRounds);
    }

    /**
     * 95% confidence interval for the mean per-round return.
     *
     * @return array{low:float,high:float}|null
     */
    public function confidenceInterval95(): ?array
    {
        $se = $this->standardError();
        if ($se === null) {
            return null;
        }

        $margin = 1.96 * $se;

        return [
            'low'  => $this->meanReturn - $margin,
            'high' => $this->meanReturn + $margin,
        ];
    }

    public function maxPayoutMinor(): int
    {
        return $this->maxPayoutMinor;
    }

    public function minPayoutMinor(): int
    {
        return $this->minPayoutMinor === PHP_INT_MAX ? 0 : $this->minPayoutMinor;
    }

    /** Largest observed per-round return as a multiple of the stake. */
    public function maxReturnMultiple(): float
    {
        return $this->maxMultiplierScaled / 100;
    }

    /** @return array<string, int> rounded return multiple => count */
    public function multiplierHistogram(): array
    {
        $histogram = $this->multiplierHistogram;
        ksort($histogram, SORT_NUMERIC);

        return $histogram;
    }

    /** @return array<string, int> feature name => rounds in which it fired */
    public function featureCounts(): array
    {
        return $this->featureCounts;
    }

    public function featureTriggerRate(): ?float
    {
        if ($this->rounds === 0) {
            return null;
        }

        return $this->featureRounds / $this->rounds;
    }

    /**
     * The report a caller renders or exports.
     *
     * Every field is prefixed with "observed" where it is a sample measurement,
     * so a consumer cannot mistake it for a theoretical value.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $ci = $this->confidenceInterval95();

        return [
            'rounds'                    => $this->rounds,
            'paid_rounds'               => $this->paidRounds,
            'total_wagered_minor'       => $this->totalWageredMinor,
            'total_returned_minor'      => $this->totalReturnedMinor,
            'net_minor'                 => $this->totalReturnedMinor - $this->totalWageredMinor,
            'observed_rtp'              => $this->round($this->observedRtp()),
            'observed_hit_frequency'    => $this->round($this->hitFrequency()),
            'observed_win_frequency'    => $this->round($this->winFrequency()),
            'observed_standard_error'   => $this->round($this->standardError()),
            'observed_rtp_ci95'         => $ci === null ? null : [
                'low'  => $this->round($ci['low']),
                'high' => $this->round($ci['high']),
            ],
            'winning_rounds'            => $this->winningRounds,
            'push_rounds'               => $this->pushRounds,
            'losing_rounds'             => $this->losingRounds,
            'hits'                      => $this->hits,
            'max_payout_minor'          => $this->maxPayoutMinor,
            'min_payout_minor'          => $this->minPayoutMinor(),
            'max_return_multiple'       => $this->maxReturnMultiple(),
            'return_distribution'       => $this->multiplierHistogram(),
            'feature_trigger_rate'      => $this->round($this->featureTriggerRate()),
            'feature_counts'            => $this->featureCounts,
        ];
    }

    private function round(?float $value): ?float
    {
        return $value === null ? null : round($value, 6);
    }
}
