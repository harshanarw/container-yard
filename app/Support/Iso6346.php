<?php

namespace App\Support;

/**
 * ISO 6346 container-number helpers — shape and check-digit.
 *
 * Format: 4 letters (owner + category) + 7 digits (6 serial + 1 check).
 * The check digit is computed from the first 10 characters; a genuine
 * mis-stencilled box can fail it, so callers decide whether a bad check digit
 * is a hard error or a soft warning.
 */
class Iso6346
{
    /** Owner-code letter weights (I, O, and some others are skipped in the table). */
    private const LETTER_VALUES = [
        'A' => 10, 'B' => 12, 'C' => 13, 'D' => 14, 'E' => 15, 'F' => 16, 'G' => 17, 'H' => 18,
        'I' => 19, 'J' => 20, 'K' => 21, 'L' => 23, 'M' => 24, 'N' => 25, 'O' => 26, 'P' => 27,
        'Q' => 28, 'R' => 29, 'S' => 30, 'T' => 31, 'U' => 32, 'V' => 34, 'W' => 35, 'X' => 36,
        'Y' => 37, 'Z' => 38,
    ];

    /** Normalise to upper-case with surrounding whitespace removed. */
    public static function normalize(?string $no): string
    {
        return strtoupper(trim((string) $no));
    }

    /** True when the number is 4 letters + 7 digits. */
    public static function matchesFormat(?string $no): bool
    {
        return (bool) preg_match('/^[A-Z]{4}[0-9]{7}$/', self::normalize($no));
    }

    /** True when the ISO 6346 check digit (11th char) is correct. */
    public static function checkDigitValid(?string $no): bool
    {
        $no = self::normalize($no);
        if (! self::matchesFormat($no)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $ch  = $no[$i];
            $val = ctype_alpha($ch) ? (self::LETTER_VALUES[$ch] ?? 0) : (int) $ch;
            $sum += $val * (2 ** $i);
        }

        return (int) $no[10] === ($sum % 11) % 10;
    }

    /** Full validity: correct shape AND correct check digit. */
    public static function isValid(?string $no): bool
    {
        return self::matchesFormat($no) && self::checkDigitValid($no);
    }
}
