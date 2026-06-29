<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\ExchangeRate;

class CurrencyService
{
    /**
     * Returns the system default currency code (e.g. 'LKR').
     */
    public static function defaultCurrency(): string
    {
        return strtoupper(CompanySetting::current()->default_currency_code ?? 'LKR');
    }

    /**
     * Look up the most recent USD → default-currency rate on or before $date.
     * Returns null when no rate has been defined yet.
     */
    public static function usdToDefault(?string $date = null): ?float
    {
        $default = static::defaultCurrency();
        if ($default === 'USD') {
            return 1.0;
        }
        return ExchangeRate::getRate('USD', $default, $date ?? today()->toDateString());
    }

    /**
     * Multiplier to convert a tariff-currency rate → default-currency (LKR) amount for storage.
     *
     *   tariff == default  →  1.0  (no conversion; LKR tariff billed in LKR)
     *   tariff == 'USD'    →  $exchangeRate  (1 USD = $exchangeRate LKR)
     *
     * This is the core bug-fix: previously always ×exchangeRate even when tariff was in LKR.
     */
    public static function tariffMultiplier(string $tariffCurrency, float $exchangeRate): float
    {
        if (strtoupper($tariffCurrency) === static::defaultCurrency()) {
            return 1.0;
        }
        return $exchangeRate;
    }

    /**
     * Multiplier to convert a default-currency (LKR) stored amount → invoice-currency for display.
     *
     *   invoice == default  →  1.0
     *   invoice == 'USD'    →  1 / $exchangeRate
     */
    public static function invoiceDisplayFactor(string $invoiceCurrency, float $exchangeRate): float
    {
        if (strtoupper($invoiceCurrency) === static::defaultCurrency()) {
            return 1.0;
        }
        return $exchangeRate > 0 ? 1.0 / $exchangeRate : 1.0;
    }

    /**
     * Validate a user-supplied exchange rate for a document in $currency.
     *
     * Base currency always returns 1.0. A foreign currency requires a positive
     * rate — throws InvalidArgumentException otherwise, so a document is never
     * persisted in a foreign currency with the rate silently defaulting to 1.0
     * (which would understate the base-currency ledger by the true rate).
     */
    public static function requireRate(?string $currency, $providedRate): float
    {
        $base = static::defaultCurrency();
        $cur  = strtoupper((string) ($currency ?: $base));

        if ($cur === $base) {
            return 1.0;
        }

        $rate = (float) ($providedRate ?? 0);
        if ($rate <= 0) {
            throw new \InvalidArgumentException(
                "A valid exchange rate is required for a {$cur} document (base currency is {$base}). "
                . "Enter the {$cur} → {$base} rate before saving."
            );
        }

        return $rate;
    }

    /**
     * Look up the rate for $currency on $date, failing if none is configured.
     * Used where the rate is auto-resolved (e.g. repair invoices) so a missing
     * rate surfaces as an error instead of silently falling back to 1.0.
     */
    public static function resolveRateOrFail(?string $currency, ?string $date = null): float
    {
        $base = static::defaultCurrency();
        $cur  = strtoupper((string) ($currency ?: $base));

        if ($cur === $base) {
            return 1.0;
        }

        $rate = ExchangeRate::getRate($cur, $base, $date ?? today()->toDateString());
        if (!$rate || $rate <= 0) {
            throw new \InvalidArgumentException(
                "No exchange rate is configured for {$cur} → {$base}. "
                . "Add one under Finance → Exchange Rates before issuing this document."
            );
        }

        return (float) $rate;
    }
}
