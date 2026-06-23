<?php

namespace App\Services\Finance;

use Carbon\Carbon;

class PaymentTermsHelper
{
    private static array $days = [
        'cod'   => 0,
        'net15' => 15,
        'net30' => 30,
        'net45' => 45,
        'net60' => 60,
    ];

    public static function dueDate(string $terms, Carbon $from): Carbon
    {
        $offset = self::$days[$terms] ?? 30;

        return (clone $from)->addDays($offset);
    }

    public static function label(string $terms): string
    {
        return match ($terms) {
            'cod'   => 'Cash on Delivery',
            'net15' => 'Net 15 Days',
            'net30' => 'Net 30 Days',
            'net45' => 'Net 45 Days',
            'net60' => 'Net 60 Days',
            default => $terms,
        };
    }
}
