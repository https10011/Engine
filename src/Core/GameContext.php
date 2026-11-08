<?php

declare(strict_types=1);

namespace CasinoEngine\Core;

use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * The service bag handed to a game module.
 *
 * Deliberately narrow. It exposes randomness, configuration, logging, a clock,
 * feature flags and — when an administrator has configured one — a probability
 * policy. It exposes nothing that can move money, so a module cannot debit a
 * wallet or write a transaction even by mistake; the only way an outcome
 * becomes currency is through the engine's round service.
 *
 * The probability policy is optional and inert by default: a module written
 * before the probability service existed keeps behaving identically, because
 * `probabilityProfile()` returns an inactive profile.
 */
final class GameContext
{
    /** @param array<string, bool> $featureFlags */
    public function __construct(
        private readonly RngServiceInterface $rng,
        private readonly GameMetadataInterface $metadata,
        private readonly GameConfigurationInterface $configuration,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
        private array $featureFlags = [],
        private readonly ?\CasinoEngine\Services\Probability\ProbabilityProfile $probability = null
    ) {
    }

    public function rng(): RngServiceInterface
    {
        return $this->rng;
    }

    public function metadata(): GameMetadataInterface
    {
        return $this->metadata;
    }

    public function config(): GameConfigurationInterface
    {
        return $this->configuration;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    public function clock(): ClockInterface
    {
        return $this->clock;
    }

    public function featureFlag(string $name): bool
    {
        return $this->featureFlags[$name] ?? false;
    }

    /**
     * The effective probability policy for this round.
     *
     * Never null: a module can call `isActive()` on it without a null check,
     * and an inactive profile means "the engine has configured nothing, play
     * the game's own mathematics unchanged".
     */
    public function probabilityProfile(): \CasinoEngine\Services\Probability\ProbabilityProfile
    {
        return $this->probability
            ?? \CasinoEngine\Services\Probability\ProbabilityProfile::inactive();
    }

    public function withConfiguration(GameConfigurationInterface $configuration): self
    {
        return new self(
            $this->rng,
            $this->metadata,
            $configuration,
            $this->logger,
            $this->clock,
            $this->featureFlags,
            $this->probability
        );
    }

    public function withProbability(\CasinoEngine\Services\Probability\ProbabilityProfile $profile): self
    {
        return new self(
            $this->rng,
            $this->metadata,
            $this->configuration,
            $this->logger,
            $this->clock,
            $this->featureFlags,
            $profile
        );
    }
}
