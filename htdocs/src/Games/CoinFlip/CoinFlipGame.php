<?php

declare(strict_types=1);

namespace CasinoEngine\Games\CoinFlip;

use CasinoEngine\Core\Contract\GameActionInterface;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Contract\GameOutcomeInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameMetadata;
use CasinoEngine\Core\Value\GameOutcome;
use CasinoEngine\Core\Value\GenericOutcomeDetail;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;

final class CoinFlipGame implements GameInterface
{
    public const ID = 'coinflip.heads-tails';

    private const HEADS = 0;

    public function metadata(): GameMetadataInterface
    {
        return new GameMetadata(
            self::ID,
            'Coin Flip',
            GameCategory::Card,
            '1.0.0',
            '1.0.0',
            'Engine Examples',
            Money::of(100),
            Money::of(100000),
            Money::of(100),
            Volatility::Low,
            2,
            0.5,
            true,
            ['example']
        );
    }

    public function category(): GameCategory
    {
        return GameCategory::Card;
    }

    public function defaultConfiguration(): GameConfigurationInterface
    {
        return CoinFlipConfiguration::fromArray([]);
    }

    public function validateConfiguration(GameConfigurationInterface $configuration): void
    {
        if (!$configuration instanceof CoinFlipConfiguration) {
            throw new ConfigurationException('CoinFlipGame expects a CoinFlipConfiguration.');
        }

        $configuration->validate();
    }

    public function createInitialState(GameContext $context): GameStateInterface
    {
        return CoinFlipState::initial();
    }

    /** @param array<string, mixed> $data */
    public function hydrateState(array $data): GameStateInterface
    {
        return CoinFlipState::fromArray($data);
    }

    public function validateAction(GameActionInterface $action, GameStateInterface $state): void
    {
        if ($action->name() !== 'flip') {
            throw new GameRuleException("Unknown action '{$action->name()}'.");
        }
        if ($action->bet() === null || $action->bet()->isZero()) {
            throw new GameRuleException('A flip requires a bet.');
        }
    }

    public function execute(
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $context
    ): GameOutcomeInterface {
        $state = $state instanceof CoinFlipState ? $state : CoinFlipState::initial();

        $call = $context->rng()->int(0, 1);
        $won = $call === self::HEADS;

        return new GameOutcome(
            new CoinFlipState($state->flipsPlayed() + 1),
            new GenericOutcomeDetail('coin_flip', [
                'call'   => 'heads',
                'result' => $won ? 'heads' : 'tails',
            ]),
            $won ? Ratio::of(2) : Ratio::zero(),
            Ratio::one()
        );
    }
}
