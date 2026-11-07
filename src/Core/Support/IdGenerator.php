<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Support;

/**
 * Generates opaque, sortable session identifiers.
 *
 * A ULID-style identifier is used rather than an auto-increment integer so a
 * session id in a URL reveals nothing about how many sessions exist and cannot
 * be guessed by incrementing. The timestamp prefix keeps ids sortable by
 * creation time, which makes index locality good on InnoDB.
 */
final class IdGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford base32

    public static function ulid(?float $time = null): string
    {
        $milliseconds = (int) floor(($time ?? microtime(true)) * 1000);

        $timePart = '';
        for ($i = 9; $i >= 0; $i--) {
            $timePart .= self::ALPHABET[($milliseconds >> ($i * 5)) & 31];
        }

        $randomPart = '';
        $bytes = random_bytes(16);
        for ($i = 0; $i < 16; $i++) {
            $randomPart .= self::ALPHABET[ord($bytes[$i]) & 31];
        }

        return $timePart . $randomPart;
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** 26-character check, useful in tests and validators. */
    public static function isValidUlid(string $value): bool
    {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value) === 1;
    }
}
