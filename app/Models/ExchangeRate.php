<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'rate_date',
        'from_currency_code',
        'to_currency_code',
        'rate',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'rate'      => 'decimal:6',
    ];

    public function fromCurrency()
    {
        return $this->belongsTo(Currency::class, 'from_currency_code', 'code');
    }

    public function toCurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency_code', 'code');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Convenience: look up the most recent rate for a given pair on or before a date
    public static function getRate(string $from, string $to, ?string $date = null): ?float
    {
        return static::where('from_currency_code', strtoupper($from))
            ->where('to_currency_code', strtoupper($to))
            ->where('rate_date', '<=', $date ?? today())
            ->orderByDesc('rate_date')
            ->value('rate');
    }
}
