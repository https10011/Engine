<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Integration;

use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Contract\SimulationStrategyInterface;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\RoundResult;
use CasinoEngine\Tests\Support\DatabaseTestCase;

/**
 * All eighteen target games, end to end, against the real database.
 *
 * The per-game unit tests prove a game's own mathematics. This proves the other
 * half: that every one of the eighteen registers, opens a session, runs a round
 * through the same `RoundService` the API uses, moves the wallet by exactly the
 * wager and the payout, and writes history — and that none of them shares state
 * with another. A game that only worked when driven directly by its own test
 * would pass those unit tests and still be unusable.
 *
 * The games are driven through `SimulationStrategyInterface` where they provide
 * one, because that is the game telling the harness how its own round is built.
 */
final class AllGamesIntegrationTest extends DatabaseTestCase
{
    /**
     * The eighteen games the directive names, by their registered ids.
     *
     * @return list<string>
     */
    private static function targetGames(): array
    {
        return [
            'jili.super-ace',
            'pgsoft.pinata-wins',
            'pgsoft.wild-bounty-showdown',
            'slots.mahjong-hit',
            'jili.golden-empire',
            'slots.sugar-bang-bang-2',
            'jili.fortune-gems-3',
            'slots.swerte-fiesta',
            'jili.super-ace-deluxe',
            'jili.fortune-garuda-1000',
            'pragmatic.sugar-rush-1000',
            'jili.fortune-gems-2',
            'pgsoft.treasure-of-aztec',
            'pragmatic.sweet-bonanza-1000',
            'jili.super-ace-2',
            'pragmatic.gates-of-olympus-1000',
            'table.blackjack',
            'table.roulette',
        ];
    }

    public function testAllEighteenGamesAreRegistered(): void
    {
        $ids = $this->engine->registry()->ids();

        foreach (self::targetGames() as $id) {
            self::assertContains($id, $ids, "The game '$id' must be registered.");
        }

        self::assertCount(18, array_unique(self::targetGames()));
    }

    public function testEveryGameHasMetadataTheApiCanPublish(): void
    {
        foreach (self::targetGames() as $id) {
            $game = $this->engine->registry()->get($id);
            $metadata = $game->metadata();

            self::assertSame($id, $metadata->id());
            self::assertNotSame('', $metadata->name());
            self::assertNotSame('', $metadata->provider());
            self::assertTrue($metadata->minBet()->isPositive(), "$id must advertise a minimum bet.");
            self::assertGreaterThanOrEqual(
                $metadata->minBet()->minorUnits(),
                $metadata->maxBet()->minorUnits(),
                "$id's maximum bet cannot be below its minimum."
            );
            self::assertGreaterThan(0, $metadata->maxWinMultiplier());
        }
    }

    public function testEveryGameOpensASessionAndRunsARoundThatMovesTheWallet(): void
    {
        foreach (self::targetGames() as $id) {
            $userId = $this->createUserWithBalance(5000000, 'player_' . md5($id));
            $game = $this->engine->registry()->get($id);
            $session = $this->openSession($userId, $game);

            $before = $this->engine->wallet()->balance($userId)->minorUnits();
            $round = $this->playOneRound($userId, $session->id(), $game);

            // A multi-action round (blackjack's deal → hit → stand, roulette's
            // place_bets → spin) wagers across several actions, so the whole
            // round is summed rather than the action that happened to settle it.
            $expected = $before - $round['bet'] + $round['payout'];
            self::assertSame(
                $expected,
                $this->engine->wallet()->balance($userId)->minorUnits(),
                "$id must move the wallet by exactly the wager and the payout."
            );
            self::assertGreaterThan(0, $round['roundId'], "$id must record a round.");
        }
    }

    public function testEveryGameRecordsHistory(): void
    {
        foreach (self::targetGames() as $id) {
            $userId = $this->createUserWithBalance(5000000, 'hist_' . md5($id));
            $game = $this->engine->registry()->get($id);
            $session = $this->openSession($userId, $game);

            $this->playOneRound($userId, $session->id(), $game);

            self::assertNotEmpty(
                $this->engine->history()->gameHistory($userId, 5),
                "$id must write a settled round to the history."
            );
        }
    }

    /**
     * A round of one game must not touch another game's session, and two
     * sessions of the same game must keep their own state. This is the check
     * that a shared cache or a module holding static state would fail.
     */
    public function testSessionsOfDifferentGamesAreIsolated(): void
    {
        $userId = $this->createUserWithBalance(5000000);

        $slot = $this->engine->registry()->get('jili.super-ace');
        $table = $this->engine->registry()->get('table.blackjack');

        $slotSession = $this->openSession($userId, $slot);
        $tableSession = $this->openSession($userId, $table);

        // Play the slot, then check the blackjack session is untouched.
        $tableBefore = $this->engine->sessions()->load($tableSession->id())->state()->toArray();
        $this->playOneRound($userId, $slotSession->id(), $slot);
        $tableAfter = $this->engine->sessions()->load($tableSession->id())->state()->toArray();

        self::assertSame(
            $tableBefore,
            $tableAfter,
            'A round of one game must not alter another game\'s session state.'
        );
        self::assertSame(0, $this->engine->sessions()->load($tableSession->id())->roundCount());
    }

    public function testTwoSessionsOfTheSameGameKeepTheirOwnState(): void
    {
        $userId = $this->createUserWithBalance(5000000);
        $game = $this->engine->registry()->get('jili.fortune-gems-2');

        $first = $this->openSession($userId, $game);
        $second = $this->openSession($userId, $game);

        $this->playOneRound($userId, $first->id(), $game);

        self::assertSame(1, $this->engine->sessions()->load($first->id())->roundCount());
        self::assertSame(
            0,
            $this->engine->sessions()->load($second->id())->roundCount(),
            'A round in one session must not advance another session of the same game.'
        );
    }

    /**
     * Every game must reject a bet outside its advertised limits without
     * touching the wallet. A game that accepted one would let a player stake
     * more than its own metadata says is allowed.
     */
    public function testEveryGameRejectsABetBelowItsMinimum(): void
    {
        foreach (self::targetGames() as $id) {
            $userId = $this->createUserWithBalance(5000000, 'low_' . md5($id));
            $game = $this->engine->registry()->get($id);
            $session = $this->openSession($userId, $game);

            $before = $this->engine->wallet()->balance($userId)->minorUnits();

            $action = $this->openingAction($game, Money::of(1, 'PHP'));

            try {
                $this->engine->rounds()->execute(
                    $this->engine->sessions()->load($session->id()),
                    $action
                );
                self::fail("$id should have rejected a bet below its minimum.");
            } catch (\CasinoEngine\Core\Exception\EngineException $e) {
                // The engine rejects a below-minimum wager with INVALID_BET; a
                // game whose round is opened by a free action (roulette's bet
                // slip) rejects it in its own validation. Both are a rejection,
                // and the assertion that matters is the one below.
                self::assertNotSame('', $e->errorCode(), "$id must report a machine-readable error code.");
            }

            self::assertSame(
                $before,
                $this->engine->wallet()->balance($userId)->minorUnits(),
                "$id must not touch the wallet when it rejects a bet."
            );
        }
    }

    // ------------------------------------------------------------- helpers

    private function openSession(int $userId, GameInterface $game): \CasinoEngine\Core\Contract\GameSessionInterface
    {
        return $this->engine->sessions()->create(
            $userId,
            $game->metadata()->id(),
            $game->createInitialState($this->engine->contextFor($game))
        );
    }

    /**
     * Plays one complete round, following the game's own continuation rule when
     * it has one, and returns the round's totals.
     *
     * @return array{bet:int, payout:int, roundId:int}
     */
    private function playOneRound(int $userId, string $sessionId, GameInterface $game): array
    {
        $bet = Money::of($game->metadata()->minBet()->minorUnits() * 10, 'PHP');

        $action = $this->openingAction($game, $bet);
        $result = $this->engine->rounds()->execute(
            $this->engine->sessions()->load($sessionId),
            $action
        );

        $totalBet = $result->bet->minorUnits();
        $totalPayout = $result->payout->minorUnits();
        $roundId = $result->roundId;

        $guard = 0;
        while (!$result->state->isComplete() && $guard++ < 60) {
            $next = $this->continuation($game, $result->state, $action);
            if ($next === null) {
                break;
            }

            $result = $this->engine->rounds()->execute(
                $this->engine->sessions()->load($sessionId),
                $next
            );
            $totalBet += $result->bet->minorUnits();
            $totalPayout += $result->payout->minorUnits();
            $roundId = $result->roundId;
            $action = $next;
        }

        return ['bet' => $totalBet, 'payout' => $totalPayout, 'roundId' => $roundId];
    }

    private function openingAction(GameInterface $game, Money $bet): GameAction
    {
        if ($game instanceof SimulationStrategyInterface) {
            return $game->simulationAction(
                $game->createInitialState($this->engine->contextFor($game)),
                $bet
            );
        }

        return match ($game->metadata()->category()) {
            \CasinoEngine\Core\Contract\GameCategory::Slot => GameAction::withBet('spin', $bet),
            default => GameAction::withBet('deal', $bet),
        };
    }

    private function continuation(
        GameInterface $game,
        GameStateInterface $state,
        GameAction $previous
    ): ?GameAction {
        if ($game instanceof SimulationStrategyInterface) {
            return $game->simulationContinuation($state, $previous);
        }

        return null;
    }
}
