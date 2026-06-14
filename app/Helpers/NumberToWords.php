<?php

namespace App\Helpers;

class NumberToWords
{
    private static array $ones = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen',
        'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private static array $tens = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    /**
     * Convert a decimal monetary amount to words.
     *
     * e.g. convert(1234567.50, 'LKR') →
     *      "One Million Two Hundred Thirty Four Thousand Five Hundred Sixty Seven Rupees and Fifty Cents Only"
     */
    public static function convert(float $amount, string $currency = 'LKR'): string
    {
        $amount = round($amount, 2);

        if ($amount < 0) {
            return 'Negative ' . self::convert(abs($amount), $currency);
        }

        $wholes = (int) floor($amount);
        $cents  = (int) round(($amount - $wholes) * 100);

        [$singular, $plural, $centSingular, $centPlural] = match (strtoupper($currency)) {
            'USD', 'SGD', 'AUD', 'CAD' => ['Dollar',  'Dollars',  'Cent',  'Cents'],
            'GBP'                       => ['Pound',   'Pounds',   'Penny', 'Pence'],
            'EUR'                       => ['Euro',    'Euros',    'Cent',  'Cents'],
            default                     => ['Rupee',   'Rupees',   'Cent',  'Cents'],
        };

        $result = self::group($wholes) . ' ' . ($wholes === 1 ? $singular : $plural);

        if ($cents > 0) {
            $result .= ' and ' . self::group($cents) . ' ' . ($cents === 1 ? $centSingular : $centPlural);
        }

        return $result . ' Only';
    }

    private static function group(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }

        $words = '';

        if ($n >= 1_000_000_000) {
            $words .= self::group((int) ($n / 1_000_000_000)) . ' Billion ';
            $n     %= 1_000_000_000;
        }
        if ($n >= 1_000_000) {
            $words .= self::group((int) ($n / 1_000_000)) . ' Million ';
            $n     %= 1_000_000;
        }
        if ($n >= 1_000) {
            $words .= self::group((int) ($n / 1_000)) . ' Thousand ';
            $n     %= 1_000;
        }
        if ($n >= 100) {
            $words .= self::$ones[(int) ($n / 100)] . ' Hundred ';
            $n     %= 100;
        }
        if ($n >= 20) {
            $words .= self::$tens[(int) ($n / 10)] . ' ';
            $n     %= 10;
        }
        if ($n > 0) {
            $words .= self::$ones[$n] . ' ';
        }

        return trim($words);
    }
}
