<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Event;

/** Names of the domain events the engine emits. */
final class EngineEvents
{
    public const GAME_REGISTERED   = 'engine.game.registered';
    public const SESSION_CREATED   = 'engine.session.created';
    public const SESSION_CLOSED    = 'engine.session.closed';
    public const ACTION_EXECUTED   = 'engine.action.executed';
    public const WAGER_PLACED      = 'engine.wager.placed';
    public const PAYOUT_PAID       = 'engine.payout.paid';
    public const FEATURE_TRIGGERED = 'engine.feature.triggered';
    public const ROUND_COMPLETED   = 'engine.round.completed';
    public const ENGINE_ERROR      = 'engine.error';

    private function __construct()
    {
    }
}
