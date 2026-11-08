<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Controller;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Services\History\HistoryServiceInterface;
use CasinoEngine\Services\Statistics\StatisticsServiceInterface;
use CasinoEngine\Services\Wallet\WalletServiceInterface;

/**
 * Read-only wallet, history and statistics endpoints.
 *
 * Every query is scoped to the authenticated user's id, taken from the token
 * context. There is no endpoint that accepts a user id, so a client cannot ask
 * for another player's balance or history even by guessing.
 */
final class WalletController extends AbstractController
{
    public function __construct(
        private readonly WalletServiceInterface $wallet,
        private readonly HistoryServiceInterface $history,
        private readonly StatisticsServiceInterface $statistics
    ) {
    }

    /**
     * @param array<string, string> $parameters
     */
    public function balance(array $parameters, Request $request, RequestContext $context): Response
    {
        return Response::ok([
            'balance' => $this->wallet->balance($context->userId())->jsonSerialize(),
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function transactions(array $parameters, Request $request, RequestContext $context): Response
    {
        $limit = $this->intQuery($request, 'limit', 50, 1, 200);
        $offset = $this->intQuery($request, 'offset', 0, 0, 100000);

        $history = $this->history->walletHistory($context->userId(), $limit, $offset);

        return Response::ok(
            ['transactions' => $history['items']],
            200,
            $this->paginationMeta($limit, $offset)
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    public function gameHistory(array $parameters, Request $request, RequestContext $context): Response
    {
        $limit = $this->intQuery($request, 'limit', 50, 1, 200);
        $offset = $this->intQuery($request, 'offset', 0, 0, 100000);
        $gameId = $request->query('game_id');
        $gameId = is_string($gameId) && $gameId !== '' ? $gameId : null;

        $history = $this->history->gameHistory($context->userId(), $limit, $offset, $gameId);

        return Response::ok(
            ['rounds' => $history['items'], 'total' => $history['total']],
            200,
            $this->paginationMeta($limit, $offset)
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    public function statistics(array $parameters, Request $request, RequestContext $context): Response
    {
        return Response::ok(['statistics' => $this->statistics->forUser($context->userId())]);
    }
}
