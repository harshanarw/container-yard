<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlEntry extends Model
{
    protected $fillable = [
        'journal_id',
        'account_id',
        'debit',
        'credit',
        'narration',
    ];

    protected $casts = [
        'debit'  => 'decimal:4',
        'credit' => 'decimal:4',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(GlJournal::class, 'journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
