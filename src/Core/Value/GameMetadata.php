<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Value;

use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Core\Exception\ValidationException;

/** Immutable descriptor for a game module. */
final class GameMetadata implements GameMetadataInterface
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly GameCategory $category,
        private readonly string $version,
        private readonly string $sdkVersion,
        private readonly string $provider,
        private readonly Money $minBet,
        private readonly Money $maxBet,
        private readonly Money $betStep,
        private readonly Volatility $volatility,
        private readonly int $maxWinMultiplier,
        private readonly ?float $rtp = null,
        private readonly bool $enabled = true,
        private readonly array $tags = []
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{1,63}$/', $id) !== 1) {
            throw new ValidationException(
                "Game id '$id' must match ^[a-z0-9][a-z0-9._-]{1,63}$."
            );
        }
        if ($minBet->isNegative() || $maxBet->isNegative() || $betStep->isNegative()) {
            throw new ValidationException('Bet limits cannot be negative.');
        }
        if ($betStep->isZero()) {
            throw new ValidationException('Bet step must be greater than zero.');
        }
        if ($maxBet->isLessThan($minBet)) {
            throw new ValidationException('maxBet cannot be lower than minBet.');
        }
        if ($maxWinMultiplier < 0) {
            throw new ValidationException('maxWinMultiplier cannot be negative.');
        }
        if ($rtp !== null && ($rtp <= 0.0 || $rtp > 1.0)) {
            throw new ValidationException('rtp, when given, must be in (0, 1].');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function category(): GameCategory
    {
        return $this->category;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function sdkVersion(): string
    {
        return $this->sdkVersion;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function minBet(): Money
    {
        return $this->minBet;
    }

    public function maxBet(): Money
    {
        return $this->maxBet;
    }

    public function betStep(): Money
    {
        return $this->betStep;
    }

    public function volatility(): Volatility
    {
        return $this->volatility;
    }

    public function maxWinMultiplier(): int
    {
        return $this->maxWinMultiplier;
    }

    public function rtp(): ?float
    {
        return $this->rtp;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'category'         => $this->category->value,
            'version'          => $this->version,
            'sdk_version'      => $this->sdkVersion,
            'provider'         => $this->provider,
            'min_bet'          => $this->minBet->jsonSerialize(),
            'max_bet'          => $this->maxBet->jsonSerialize(),
            'bet_step'         => $this->betStep->jsonSerialize(),
            'volatility'       => $this->volatility->value,
            'max_win_multiplier' => $this->maxWinMultiplier,
            'rtp'              => $this->rtp,
            'enabled'          => $this->enabled,
            'tags'             => $this->tags,
        ];
    }
}
