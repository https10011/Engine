<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Admin;

use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Database\ConnectionInterface;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Persistence\Repository\WalletRepository;
use CasinoEngine\Services\Auth\PhoneNumber;

/**
 * Read models and audited mutations used by the dedicated administrator page.
 *
 * This service deliberately uses the existing wallet repository and ledger
 * rather than writing balances directly, so an administrator adjustment is
 * subject to the same row lock, version check and transaction record as a
 * settled game round.
 */
final class AdminService
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly UserRepository $users,
        private readonly WalletRepository $wallets,
        private readonly GameRegistryInterface $games,
        private readonly GameControlService $controls,
        private readonly ClockInterface $clock
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $pdo = $this->connection->pdo();

        $totals = $pdo->query(
            "SELECT
                COUNT(*) AS users,
                SUM(status = 'active') AS active_users,
                SUM(status = 'suspended') AS suspended_users,
                SUM(role = 'admin') AS admins
             FROM users"
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        $wallet = $pdo->query(
            'SELECT COALESCE(SUM(balance_minor), 0) AS credits, COUNT(*) AS wallets FROM wallets'
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        $activity = $pdo->query(
            'SELECT COUNT(*) AS rounds, COALESCE(SUM(bet_minor), 0) AS wagered,
                    COALESCE(SUM(payout_minor), 0) AS returned,
                    COALESCE(SUM(net_minor), 0) AS net
             FROM game_rounds'
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        $recentlyActive = $pdo->query(
            "SELECT COUNT(DISTINCT user_id) FROM game_sessions
             WHERE status = 'active' AND updated_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE"
        )->fetchColumn();

        $friendly = 0;
        foreach ($this->games->all(false) as $metadata) {
            if ($this->controls->isFriendlyPlay($metadata->id())) {
                $friendly++;
            }
        }

        return [
            'total_users'        => (int) ($totals['users'] ?? 0),
            'active_users'       => (int) ($totals['active_users'] ?? 0),
            'suspended_users'    => (int) ($totals['suspended_users'] ?? 0),
            'admins'             => (int) ($totals['admins'] ?? 0),
            'online_users'       => (int) $recentlyActive,
            'credits_minor'      => (int) ($wallet['credits'] ?? 0),
            'wallets'            => (int) ($wallet['wallets'] ?? 0),
            'games'              => count($this->games->all(false)),
            'friendly_games'     => $friendly,
            'rounds'             => (int) ($activity['rounds'] ?? 0),
            'wagered_minor'      => (int) ($activity['wagered'] ?? 0),
            'returned_minor'     => (int) ($activity['returned'] ?? 0),
            'net_minor'          => (int) ($activity['net'] ?? 0),
            'recent_users'       => $this->recentUsers(6),
            'recent_rounds'      => $this->recentRounds(8),
            'game_activity'      => $this->gameActivity(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function users(string $search = '', int $limit = 100): array
    {
        $sql = 'SELECT u.id, u.username, u.phone, u.display_name, u.age, u.status, u.role,
                       u.created_at, w.balance_minor, w.currency, w.scale,
                       (SELECT COUNT(*) FROM game_rounds r WHERE r.user_id = u.id) AS rounds
                FROM users u
                LEFT JOIN wallets w ON w.user_id = u.id';
        $parameters = [];

        $search = trim($search);
        if ($search !== '') {
            $sql .= ' WHERE u.phone LIKE ? OR u.display_name LIKE ? OR u.username LIKE ? OR CAST(u.id AS CHAR) = ?';
            $like = '%' . $search . '%';
            $parameters = [$like, $like, $like, $search];

            // An operator usually searches with the local `09...` form while
            // the database stores the canonical `+63...` value.
            $canonicalPhone = PhoneNumber::tryNormalize($search);
            if ($canonicalPhone !== null) {
                $sql .= ' OR u.phone = ?';
                $parameters[] = $canonicalPhone;
            }
        }

        $limit = max(1, min($limit, 500));
        $sql .= ' ORDER BY u.created_at DESC, u.id DESC LIMIT ' . $limit;

        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function user(int $userId): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT u.id, u.username, u.phone, u.display_name, u.age, u.status, u.role,
                    u.created_at, u.updated_at, w.id AS wallet_id, w.balance_minor, w.currency, w.scale
             FROM users u LEFT JOIN wallets w ON w.user_id = u.id
             WHERE u.id = ?'
        );
        $statement->execute([$userId]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['transactions'] = $this->wallets->transactionsForUser($userId, 20);

        return $row;
    }

    /**
     * Applies an audited credit adjustment.
     *
     * @param array<string, mixed> $admin
     */
    public function adjustCredits(int $userId, int $amountMinor, string $reason, array $admin): int
    {
        if ($amountMinor === 0) {
            throw new ValidationException('Enter a non-zero credit adjustment.');
        }
        if (abs($amountMinor) > 10_000_000) {
            throw new ValidationException('A single adjustment cannot exceed 100,000 credits.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 200) {
            throw new ValidationException('Add a reason between 3 and 200 characters.');
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new ValidationException('That account no longer exists.');
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $this->connection->beginTransaction();
        try {
            $wallet = $this->wallets->lockByUserId($userId);
            if ($wallet === null) {
                throw new ValidationException('That account does not have a wallet.');
            }

            $current = (int) $wallet['balance_minor'];
            $newBalance = $current + $amountMinor;
            if ($newBalance < 0) {
                throw new ValidationException('The adjustment would make the balance negative.');
            }

            if (!$this->wallets->updateBalance((int) $wallet['id'], (int) $wallet['version'], $newBalance, $now)) {
                throw new ValidationException('The balance changed while saving. Reload and try again.');
            }

            $reference = 'admin:' . $userId . ':' . bin2hex(random_bytes(8));
            $this->wallets->recordTransaction(
                (int) $wallet['id'],
                $userId,
                null,
                $amountMinor > 0 ? 'credit' : 'debit',
                'admin_adjustment',
                abs($amountMinor),
                $newBalance,
                $reference,
                $now
            );

            $this->audit(
                $userId,
                'admin.credits.adjusted',
                [
                    'admin_username' => (string) ($admin['username'] ?? ''),
                    'amount_minor'   => $amountMinor,
                    'balance_before' => $current,
                    'balance_after'  => $newBalance,
                    'reason'         => $reason,
                    'reference'      => $reference,
                ],
                $now
            );

            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        return $newBalance;
    }

    /**
     * @param array<string, mixed> $admin
     */
    public function setStatus(int $userId, string $status, array $admin): void
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new ValidationException('Unknown account status.');
        }

        if ($userId === (int) ($admin['id'] ?? 0) && $status !== 'active') {
            throw new ValidationException('You cannot suspend the administrator account you are signed in with.');
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new ValidationException('That account no longer exists.');
        }
        if ((string) ($user['role'] ?? 'player') === 'admin' && $status !== 'active') {
            throw new ValidationException('Suspend administrator accounts through the server console.');
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->users->updateStatus($userId, $status, $now);

        $this->audit(
            $userId,
            'admin.account.status',
            [
                'admin_username' => (string) ($admin['username'] ?? ''),
                'from'           => (string) ($user['status'] ?? ''),
                'to'             => $status,
            ],
            $now
        );
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $statement = $this->connection->pdo()->query(
            'SELECT a.id, a.user_id, a.event, a.context, a.ip_address, a.created_at, u.display_name
             FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT ' . $limit
        );

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    private function recentUsers(int $limit): array
    {
        return $this->connection->pdo()->query(
            'SELECT u.id, u.phone, u.display_name, u.created_at, w.balance_minor
             FROM users u LEFT JOIN wallets w ON w.user_id = u.id
             WHERE u.role = "player"
             ORDER BY u.id DESC LIMIT ' . max(1, min($limit, 20))
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    private function recentRounds(int $limit): array
    {
        return $this->connection->pdo()->query(
            'SELECT r.id, r.game_id, r.bet_minor, r.payout_minor, r.net_minor, r.created_at,
                    u.display_name
             FROM game_rounds r LEFT JOIN users u ON u.id = r.user_id
             ORDER BY r.id DESC LIMIT ' . max(1, min($limit, 30))
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    private function gameActivity(): array
    {
        return $this->connection->pdo()->query(
            'SELECT game_id, COUNT(*) AS rounds, COALESCE(SUM(bet_minor), 0) AS wagered,
                    COALESCE(SUM(payout_minor), 0) AS returned
             FROM game_rounds
             WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY
             GROUP BY game_id ORDER BY rounds DESC LIMIT 8'
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $context */
    private function audit(?int $userId, string $event, array $context, string $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO audit_log (user_id, event, context, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $userId,
            $event,
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $now,
        ]);
    }
}
