<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

/** A change in a game feature's state during one action. */
final class FeatureTransition
{
    public const STARTED  = 'started';
    public const UPDATED  = 'updated';
    public const ENDED    = 'ended';
    public const TRIGGERED = 'triggered';

    /**
     * @param array<string, mixed> $detail
     */
    public function __construct(
        public readonly string $type,
        public readonly string $status,
        public readonly int $remaining = 0,
        public readonly array $detail = []
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type'      => $this->type,
            'status'    => $this->status,
            'remaining' => $this->remaining,
            'detail'    => $this->detail,
        ];
    }
}
