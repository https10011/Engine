<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Table\Roulette;

use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Exception\ValidationException;

/**
 * Roulette round state.
 *
 * Roulette is played in two steps: the player places bets on the layout, then
 * the wheel turns. The state therefore carries the *uncommitted* bet slip
 * between the two, so a round that is interrupted — the player closes the
 * browser after placing bets but before spinning — resumes with the slip
 * intact. Committing is the spin; nothing is wagered until it happens.
 *
 * The last spin's result is kept so the interface can show the previous
 * number and its colour while the next slip is being built, which is how a real
 * table works: the number that just landed stays on the display.
 */
final class RouletteState implements GameStateInterface
{
    public const SCHEMA_VERSION = 1;

    /** The player is building a bet slip; nothing is wagered yet. */
    public const PHASE_BETTING = 'betting';

    /** A spin has settled; the slip is empty and ready to be rebuilt. */
    public const PHASE_SETTLED = 'settled';

    /**
     * @param list<array{type:string,numbers:list<string>,stake_minor:int}> $bets
     * @param array<string, mixed>|null $lastSpin
     * @param list<string> $history Winning pockets, most recent last
     */
    public function __construct(
        private readonly array $bets = [],
        private readonly ?array $lastSpin = null,
        private readonly array $history = [],
        private readonly string $phase = self::PHASE_SETTLED,
        private readonly int $spinsPlayed = 0
    ) {
    }

    public static function initial(): self
    {
        return new self();
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * A round is complete once the wheel has turned.
     *
     * A slip that has been placed but not spun is *not* complete: the round
     * still owes the player a spin, and the simulation driver relies on this to
     * know it must keep going rather than treating the placed slip as the whole
     * round.
     */
    public function isComplete(): bool
    {
        return $this->phase === self::PHASE_SETTLED;
    }

    public function phase(): string
    {
        return $this->phase;
    }

    /** @return list<array{type:string,numbers:list<string>,stake_minor:int}> */
    public function bets(): array
    {
        return $this->bets;
    }

    public function totalStakeMinor(): int
    {
        $total = 0;
        foreach ($this->bets as $bet) {
            $total += (int) $bet['stake_minor'];
        }

        return $total;
    }

    /** @return array<string, mixed>|null */
    public function lastSpin(): ?array
    {
        return $this->lastSpin;
    }

    /** @return list<string> */
    public function history(): array
    {
        return $this->history;
    }

    public function spinsPlayed(): int
    {
        return $this->spinsPlayed;
    }

    /**
     * @param list<array{type:string,numbers:list<string>,stake_minor:int}> $bets
     */
    public function withBets(array $bets): self
    {
        return new self($bets, $this->lastSpin, $this->history, self::PHASE_BETTING, $this->spinsPlayed);
    }

    /** @param array<string, mixed> $spin */
    public function settled(array $spin, string $pocket): self
    {
        // The history is capped so a long session's state row cannot grow
        // without bound; the full history lives in the round table.
        $history = $this->history;
        $history[] = $pocket;
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }

        return new self([], $spin, array_values($history), self::PHASE_SETTLED, $this->spinsPlayed + 1);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema'         => self::SCHEMA_VERSION,
            'bets'           => $this->bets,
            'last_spin'      => $this->lastSpin,
            'history'        => $this->history,
            'phase'          => $this->phase,
            'spins_played'   => $this->spinsPlayed,
            'total_stake'    => $this->totalStakeMinor(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        $version = (int) ($data['schema'] ?? self::SCHEMA_VERSION);
        if ($version > self::SCHEMA_VERSION) {
            throw new ValidationException('This session was created by a newer engine version.');
        }

        $bets = [];
        $rawBets = $data['bets'] ?? [];
        if (is_array($rawBets)) {
            foreach ($rawBets as $raw) {
                if (!is_array($raw)) {
                    continue;
                }

                $numbers = $raw['numbers'] ?? [];
                $bets[] = [
                    'type'        => (string) ($raw['type'] ?? ''),
                    'numbers'     => is_array($numbers) ? array_values(array_map('strval', $numbers)) : [],
                    'stake_minor' => (int) ($raw['stake_minor'] ?? 0),
                ];
            }
        }

        $history = $data['history'] ?? [];

        return new self(
            $bets,
            is_array($data['last_spin'] ?? null) ? $data['last_spin'] : null,
            is_array($history) ? array_values(array_map('strval', $history)) : [],
            (string) ($data['phase'] ?? self::PHASE_SETTLED),
            (int) ($data['spins_played'] ?? 0)
        );
    }
}
