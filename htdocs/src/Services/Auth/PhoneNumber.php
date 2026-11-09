<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Auth;

use CasinoEngine\Core\Exception\ValidationException;

/**
 * Normalises player phone numbers to one comparable E.164-style string.
 *
 * The project is used with Philippine numbers, so the common local forms are
 * canonicalised to +63:
 *
 *   0917 123 4567  ->  +639171234567
 *   9171234567     ->  +639171234567
 *   +63 917 1234567 -> +639171234567
 *
 * Other international numbers keep their country code and digits. The stored
 * value always starts with '+', so the unique index catches the same number
 * entered with different spacing, dashes or a leading 00.
 */
final class PhoneNumber
{
    /** Returns the canonical form, or null when the value is not a phone number. */
    public static function tryNormalize(string $input): ?string
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($trimmed, '00')) {
            $digits = substr($digits, 2);
        }

        // Philippine local forms. The 09 form is the one people type most.
        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            $digits = '63' . substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '63' . $digits;
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return '+' . $digits;
    }

    /** Returns the canonical form or throws a field-level validation error. */
    public static function normalize(string $input): string
    {
        $normalized = self::tryNormalize($input);
        if ($normalized === null) {
            throw new ValidationException(
                'Enter a valid phone number, for example 0917 123 4567 or +63 917 123 4567.'
            );
        }

        return $normalized;
    }
}
