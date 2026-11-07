<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Value;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Contract\GameOutcomeDetailInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use JsonSerializable;

/**
 * The engine's answer for one executed action.
 *
 * This is what a caller sees: what the game decided, what it cost, what it
 * returned, and the balance afterwards. Every field is produced by the engine
 * after the round committed, so a caller can render it but never influence it.
 */
final class RoundResult implements JsonSerializable
{
    /**
     * @param list<FeatureTransition> $featureTransitions
     * @param list<array{name:string,payload:array<string,mixed>}> $events
     */
    public function __construct(
        public readonly int $roundId,
        public readonly string $sessionId,
        public readonly string $gameId,
        public readonly int $roundNumber,
        public readonly string $action,
        public readonly Money $bet,
        public readonly Money $payout,
        public readonly Money $net,
        public readonly Ratio $payoutMultiplier,
        public readonly Money $balanceAfter,
        public readonly GameStateInterface $state,
        public readonly GameOutcomeDetailInterface $detail,
        public readonly array $featureTransitions = [],
        public readonly array $events = [],
        public readonly bool $replayed = false
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'round_id'            => $this->roundId,
            'session_id'          => $this->sessionId,
            'game_id'             => $this->gameId,
            'round_number'        => $this->roundNumber,
            'action'              => $this->action,
            'bet'                 => $this->bet->jsonSerialize(),
            'payout'              => $this->payout->jsonSerialize(),
            'net'                 => $this->net->jsonSerialize(),
            'payout_multiplier'   => $this->payoutMultiplier->jsonSerialize(),
            'balance_after'       => $this->balanceAfter->jsonSerialize(),
            'state'               => $this->state->toArray(),
            'result'              => [
                'type' => $this->detail->type(),
                'data' => $this->detail->toArray(),
            ],
            'features'            => array_map(
                static fn (FeatureTransition $t): array => $t->toArray(),
                $this->featureTransitions
            ),
            'replayed'            => $this->replayed,
        ];
    }

    /**
     * A result reconstructed from a stored idempotency response.
     *
     * Replayed results are marked as such so a caller can tell a fresh
     * settlement from a retry of an earlier one.
     *
     * @param array<string, mixed> $response
     */
    public static function fromStoredResponse(array $response): self
    {
        $currency = (string) ($response['bet']['currency'] ?? 'PHP');
        $scale = (int) ($response['bet']['scale'] ?? 2);

        return new self(
            (int) ($response['round_id'] ?? 0),
            (string) ($response['session_id'] ?? ''),
            (string) ($response['game_id'] ?? ''),
            (int) ($response['round_number'] ?? 0),
            (string) ($response['action'] ?? ''),
            Money::of((int) ($response['bet']['amount'] ?? 0), $currency, $scale),
            Money::of((int) ($response['payout']['amount'] ?? 0), $currency, $scale),
            Money::of((int) ($response['net']['amount'] ?? 0), $currency, $scale),
            Ratio::of(
                (int) ($response['payout_multiplier']['numerator'] ?? 0),
                max(1, (int) ($response['payout_multiplier']['denominator'] ?? 1))
            ),
            Money::of((int) ($response['balance_after']['amount'] ?? 0), $currency, $scale),
            new \CasinoEngine\Core\Value\RawGameState(
                is_array($response['state'] ?? null) ? $response['state'] : []
            ),
            new GenericOutcomeDetail(
                (string) ($response['result']['type'] ?? 'replayed'),
                is_array($response['result']['data'] ?? null) ? $response['result']['data'] : []
            ),
            [],
            [],
            true
        );
    }
}
