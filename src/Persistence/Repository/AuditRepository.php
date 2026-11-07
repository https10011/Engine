<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

/** Security-relevant audit trail. */
final class AuditRepository extends AbstractRepository
{
    /** @param array<string, mixed> $context */
    public function record(?int $userId, string $event, array $context, ?string $ipAddress, string $now): void
    {
        $this->execute(
            'INSERT INTO audit_log (user_id, event, context, ip_address, created_at) VALUES (?, ?, ?, ?, ?)',
            [$userId, $event, self::encodeJson($context), $ipAddress, $now]
        );
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));

        return $this->fetchAll(
            'SELECT id, user_id, event, context, ip_address, created_at FROM audit_log ORDER BY id DESC LIMIT ' . $limit
        );
    }
}
