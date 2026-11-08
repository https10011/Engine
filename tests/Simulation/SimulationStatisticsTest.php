<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Simulation;

use CasinoEngine\Simulation\SimulationStatistics;
use PHPUnit\Framework\TestCase;

/**
 * The statistics a simulation reports.
 *
 * A simulation is only useful if its arithmetic is right, and an RTP that
 * silently divided by the wrong denominator would look plausible. These cases
 * use small numbers where the answer can be worked out by hand.
 */
final class SimulationStatisticsTest extends TestCase
{
    public function testEmptyStatisticsReportNullRtp(): void
    {
        $statistics = new SimulationStatistics();

        self::assertSame(0, $statistics->rounds());
        self::assertNull($statistics->observedRtp(), 'An empty sample has no RTP, not a zero one.');
    }

    public function testRtpIsReturnedOverWagered(): void
    {
        $statistics = new SimulationStatistics();
        // Two rounds, 100 staked each, 150 returned in total.
        $statistics->record(100, 100);
        $statistics->record(100, 50);

        self::assertSame(200, $statistics->totalWageredMinor());
        self::assertSame(150, $statistics->totalReturnedMinor());
        self::assertSame(0.75, $statistics->observedRtp());
    }

    public function testFreeRoundsDoNotDistortRtp(): void
    {
        $statistics = new SimulationStatistics();
        // A paid spin of 100 returning 0, then a free spin wagering 0 that
        // returns 300. The return is 3.00x the stake actually paid, and the
        // free round must not be counted as a paid round.
        $statistics->record(100, 0);
        $statistics->record(0, 300);

        self::assertSame(2, $statistics->rounds());
        self::assertSame(1, $statistics->paidRounds());
        self::assertSame(100, $statistics->totalWageredMinor());
        self::assertSame(3.0, $statistics->observedRtp());
    }

    public function testHitAndWinFrequencyDifferWhenPushesExist(): void
    {
        $statistics = new SimulationStatistics();
        // One win (returns more than staked), one push (returns exactly the
        // stake), two losses.
        $statistics->record(100, 200);
        $statistics->record(100, 100);
        $statistics->record(100, 0);
        $statistics->record(100, 0);

        self::assertSame(0.5, $statistics->hitFrequency(), 'A push is a hit but not a win.');
        self::assertSame(0.25, $statistics->winFrequency());
    }

    public function testStandardErrorMatchesTheSampleFormula(): void
    {
        $statistics = new SimulationStatistics();
        // Returns per unit staked: 0, 0, 0, 4. Mean 1, sample variance 4.
        $statistics->record(100, 0);
        $statistics->record(100, 0);
        $statistics->record(100, 0);
        $statistics->record(100, 400);

        // standard error = sqrt(variance / n) = sqrt(4 / 4) = 1.
        self::assertEqualsWithDelta(1.0, $statistics->standardError(), 1e-9);
    }

    public function testConfidenceIntervalBracketsTheMean(): void
    {
        $statistics = new SimulationStatistics();
        for ($i = 0; $i < 1000; $i++) {
            $statistics->record(100, $i % 2 === 0 ? 0 : 200);
        }

        $interval = $statistics->confidenceInterval95();

        self::assertNotNull($interval);
        self::assertLessThan($statistics->observedRtp(), $interval['low']);
        self::assertGreaterThan($statistics->observedRtp(), $interval['high']);
    }

    public function testExtremesAreTracked(): void
    {
        $statistics = new SimulationStatistics();
        $statistics->record(100, 0);
        $statistics->record(100, 500);
        $statistics->record(100, 1200);

        self::assertSame(1200, $statistics->maxPayoutMinor());
        self::assertSame(0, $statistics->minPayoutMinor());
        self::assertSame(12.0, $statistics->maxReturnMultiple());
    }

    public function testFeatureCountsAccumulate(): void
    {
        $statistics = new SimulationStatistics();
        // The contract is a list of feature names that fired in the round;
        // each is counted once per round even if it fired several times.
        $statistics->record(100, 0, ['cascade:updated']);
        $statistics->record(100, 0, ['cascade:updated', 'free_spins:started']);

        $counts = $statistics->featureCounts();

        self::assertSame(2, $counts['cascade:updated']);
        self::assertSame(1, $counts['free_spins:started']);
        self::assertSame(1.0, $statistics->featureTriggerRate());
    }

    public function testReportMarksFiguresAsObserved(): void
    {
        $statistics = new SimulationStatistics();
        $statistics->record(100, 50);

        $report = $statistics->toArray();

        self::assertSame(1, $report['rounds']);
        self::assertSame(0.5, $report['observed_rtp']);
        self::assertArrayHasKey('observed_rtp_ci95', $report);
    }

    /**
     * The three separately-configured trigger probabilities must be counted
     * separately. Deriving them from the aggregate feature rate would report a
     * jackpot rate inflated by every bonus wheel, which is exactly the metric
     * confusion the directive forbids.
     */
    public function testTriggerRatesAreCountedSeparatelyPerEvent(): void
    {
        $statistics = new SimulationStatistics();
        $statistics->record(100, 0, ['jackpot:started']);
        $statistics->record(100, 0, ['bonus_wheel:started']);
        $statistics->record(100, 0, ['free_spins:started']);
        $statistics->record(100, 0, ['jackpot:started', 'free_spins:started']);
        $statistics->record(100, 0, ['free_spins:ended']);

        self::assertSame(2, $statistics->jackpotTriggers());
        self::assertSame(1, $statistics->bonusTriggers());
        self::assertSame(2, $statistics->freeSpinTriggers());

        self::assertSame(0.4, $statistics->jackpotTriggerRate());
        self::assertSame(0.2, $statistics->bonusTriggerRate());
        self::assertSame(0.4, $statistics->freeSpinTriggerRate());
    }

    /**
     * A feature that ends must not be counted as a trigger. A free spin that
     * has just finished reports `free_spins:ended`, and counting it would make
     * the free-spin rate climb with the length of every feature.
     */
    public function testAFeatureThatEndsIsNotATrigger(): void
    {
        $statistics = new SimulationStatistics();
        $statistics->record(100, 0, ['free_spins:ended', 'bonus_wheel:updated']);

        self::assertSame(0, $statistics->freeSpinTriggers());
        self::assertSame(0, $statistics->bonusTriggers());
    }

    public function testTriggerCountsAppearInTheReport(): void
    {
        $statistics = new SimulationStatistics();
        $statistics->record(100, 0, ['jackpot:started']);

        $report = $statistics->toArray();

        self::assertSame(1, $report['jackpot_triggers']);
        self::assertSame(1.0, $report['jackpot_trigger_rate']);
        self::assertArrayHasKey('bonus_trigger_rate', $report);
        self::assertArrayHasKey('free_spin_trigger_rate', $report);
    }
}
