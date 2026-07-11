<?php

namespace App\Services;

use App\Models\CompanySetting;

/**
 * Builds free wa.me "click-to-chat" links (no WhatsApp API). Opening the URL
 * launches WhatsApp pre-addressed to the number with a pre-filled message;
 * the sender just taps Send.
 */
class WhatsAppLink
{
    private static ?string $dial = null;

    /** The company's country dialling code, digits only (cached per request). */
    private static function dialCode(): string
    {
        if (self::$dial === null) {
            $code = optional(CompanySetting::current()->countryInfo)->phone_code;
            self::$dial = preg_replace('/\D+/', '', (string) $code) ?: '';
        }

        return self::$dial;
    }

    /** Normalise a local/loose phone to international digits (no +, no leading 0). */
    public static function toInternational(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }

        $dial = self::dialCode();

        // Already carries the country code.
        if ($dial !== '' && str_starts_with($digits, $dial)) {
            return $digits;
        }
        // Local number with a trunk 0 → swap the 0 for the country code.
        if ($dial !== '' && str_starts_with($digits, '0')) {
            return $dial . substr($digits, 1);
        }

        return $digits; // best effort — assume already international
    }

    /** Build a wa.me click-to-chat URL with a pre-filled message, or null. */
    public static function chatUrl(?string $phone, string $message): ?string
    {
        $intl = self::toInternational($phone);
        if (! $intl) {
            return null;
        }

        return 'https://wa.me/' . $intl . '?text=' . rawurlencode($message);
    }
}
