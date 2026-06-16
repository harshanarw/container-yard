<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'parent_id', 'code', 'name', 'classification', 'account_subtype',
        'normal_balance', 'is_posting', 'is_control', 'is_receivable',
        'is_payable', 'is_cash_bank', 'opening_balance', 'opening_balance_type',
        'is_active', 'is_system', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'is_posting'    => 'boolean',
        'is_control'    => 'boolean',
        'is_receivable' => 'boolean',
        'is_payable'    => 'boolean',
        'is_cash_bank'  => 'boolean',
        'is_active'     => 'boolean',
        'is_system'     => 'boolean',
        'opening_balance' => 'decimal:4',
    ];

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('code');
    }

    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    public function mappings()
    {
        return $this->hasMany(AccountMapping::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->code} — {$this->name}";
    }

    public static function classificationLabel(string $c): string
    {
        return match ($c) {
            'asset'     => 'Asset',
            'liability' => 'Liability',
            'equity'    => 'Equity',
            'income'    => 'Income',
            'expense'   => 'Expense',
            default     => ucfirst($c),
        };
    }

    public static function classificationBadge(string $c): string
    {
        return match ($c) {
            'asset'     => 'primary',
            'liability' => 'danger',
            'equity'    => 'success',
            'income'    => 'info',
            'expense'   => 'warning',
            default     => 'secondary',
        };
    }
}
