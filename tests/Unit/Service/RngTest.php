<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Unit\Service;

use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Services\Rng\RngFactory;
use CasinoEngine\Services\Rng\SeededRandomGenerator;
use CasinoEngine\Services\Rng\SecureRandomGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Randomness: the properties an authoritative outcome depends on.
 *
 * The critical guarantees are reproducibility under a seed, uniformity of the
 * integer range, and that the deterministic generator cannot be constructed in
 * production.
 */
final class RngTest extends TestCase
{
    public function testSameSeedProducesSameStream(): void
    {
        $a = new SeededRandomGenerator(42);
        $b = new SeededRandomGenerator(42);

        for ($i = 0; $i < 100; $i++) {
            self::assertSame($a->int(0, 1000), $b->int(0, 1000));
        }
    }

    public function testDifferentSeedsDiverge(): void
    {
        $a = new SeededRandomGenerator(1);
        $b = new SeededRandomGenerator(2);

        $differences = 0;
        for ($i = 0; $i < 100; $i++) {
            if ($a->int(0, 1000000) !== $b->int(0, 1000000)) {
                $differences++;
            }
        }

        self::assertGreaterThan(90, $differences);
    }

    public function testSeededGeneratorRefusesProduction(): void
    {
        $this->expectException(ValidationException::class);

        new SeededRandomGenerator(1, 'production');
    }

    public function testFactoryIgnoresSeedInProduction(): void
    {
        $config = \CasinoEngine\Core\Bootstrap::config();
        $production = $config->with('app.env', 'production');

        $rng = RngFactory::forEnvironment($production, 42);

        self::assertInstanceOf(SecureRandomGenerator::class, $rng);
        self::assertFalse($rng->isDeterministic());
    }

    public function testFactoryHonoursSeedOutsideProduction(): void
    {
        $rng = RngFactory::forEnvironment(\CasinoEngine\Core\Bootstrap::config(), 42);

        self::assertTrue($rng->isDeterministic());
        self::assertSame('seeded-xoshiro128', $rng->describe());
    }

    public function testIntStaysInRange(): void
    {
        $rng = new SeededRandomGenerator(7);

        for ($i = 0; $i < 5000; $i++) {
            $value = $rng->int(-5, 5);
            self::assertGreaterThanOrEqual(-5, $value);
            self::assertLessThanOrEqual(5, $value);
        }
    }

    public function testIntIsReasonablyUniform(): void
    {
        $rng = new SeededRandomGenerator(11);
        $buckets = array_fill(0, 10, 0);
        $draws = 100000;

        for ($i = 0; $i < $draws; $i++) {
            $buckets[$rng->int(0, 9)]++;
        }

        // Each bucket should hold about 10%. A chi-square-free sanity bound of
        // 5% either side is generous enough never to flake on a good generator.
        $expected = $draws / 10;
        foreach ($buckets as $count) {
            self::assertGreaterThan($expected * 0.95, (float) $count);
            self::assertLessThan($expected * 1.05, (float) $count);
        }
    }

    public function testShuffleKeepsEveryElement(): void
    {
        $rng = new SeededRandomGenerator(3);
        $input = range(1, 50);
        $shuffled = $rng->shuffle($input);

        sort($shuffled);

        self::assertSame($input, $shuffled);
    }

    public function testShuffleIsReproducible(): void
    {
        $input = range(1, 100);

        self::assertSame(
            (new SeededRandomGenerator(9))->shuffle($input),
            (new SeededRandomGenerator(9))->shuffle($input)
        );
    }

    public function testWeightedIndexRespectsWeights(): void
    {
        $rng = new SeededRandomGenerator(5);
        $counts = [0, 0];

        for ($i = 0; $i < 10000; $i++) {
            $counts[$rng->weightedIndex([9, 1])]++;
        }

        // The 9:1 weighting should put roughly 90% in the first bucket.
        self::assertGreaterThan(8500, $counts[0]);
        self::assertLessThan(9500, $counts[0]);
    }

    public function testWeightedIndexRejectsAllZeroWeights(): void
    {
        $this->expectException(ValidationException::class);

        (new SeededRandomGenerator(1))->weightedIndex([0, 0]);
    }

    public function testSecureGeneratorIsNotDeterministic(): void
    {
        $rng = new SecureRandomGenerator();

        self::assertFalse($rng->isDeterministic());
        self::assertSame('secure-random-int', $rng->describe());
    }

    public function testSecureGeneratorProducesDifferentValues(): void
    {
        $rng = new SecureRandomGenerator();

        self::assertNotSame($rng->int(0, PHP_INT_MAX), $rng->int(0, PHP_INT_MAX));
    }
}
