<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Controller;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Core\Exception\ValidationException;

/**
 * Shared request-parsing helpers for controllers.
 *
 * Controllers stay thin: they validate input shape, call one engine service
 * and return the service's result. No controller contains game logic, and no
 * controller writes SQL. These helpers exist so the same "is this a valid
 * integer in range" rule is applied identically everywhere.
 */
abstract class AbstractController
{
    protected function intQuery(Request $request, string $key, int $default, int $min, int $max): int
    {
        $value = $request->query($key);
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_scalar($value) || preg_match('/^-?\d+$/', (string) $value) !== 1) {
            throw new ValidationException("The query parameter '$key' must be an integer.");
        }

        $int = (int) $value;
        if ($int < $min || $int > $max) {
            throw new ValidationException("The query parameter '$key' must be between $min and $max.");
        }

        return $int;
    }

    /** @return array<string, mixed> */
    protected function paginationMeta(int $limit, int $offset): array
    {
        return ['limit' => $limit, 'offset' => $offset];
    }
}
