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
}
