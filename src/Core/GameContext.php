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
 * Deliberately narrow. It exposes randomness, configuration, logging, a clock
 * and feature flags — and nothing that can move money. A module therefore
 * cannot debit a wallet or write a transaction even by mistake; the only way
 * an outcome becomes currency is through the engine's round service.
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
        private array $featureFlags = []
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

    public function withConfiguration(GameConfigurationInterface $configuration): self
    {
        return new self(
            $this->rng,
            $this->metadata,
            $configuration,
            $this->logger,
            $this->clock,
            $this->featureFlags
        );
    }
}
