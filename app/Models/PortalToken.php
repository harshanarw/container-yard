<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PortalToken extends Model
{
    protected $fillable = [
        'tokenable_type', 'tokenable_id',
        'token', 'email', 'expires_at',
        'first_accessed_at', 'revoked_at', 'created_by',
    ];

    protected $casts = [
        'expires_at'        => 'datetime',
        'first_accessed_at' => 'datetime',
        'revoked_at'        => 'datetime',
    ];

    public function tokenable()
    {
        return $this->morphTo();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generate(Model $model, string $email, int $expiryDays = 30): self
    {
        return static::create([
            'tokenable_type' => get_class($model),
            'tokenable_id'   => $model->getKey(),
            'token'          => Str::random(64),
            'email'          => $email,
            'expires_at'     => now()->addDays($expiryDays),
            'created_by'     => auth()->id(),
        ]);
    }

    public function isValid(): bool
    {
        return is_null($this->revoked_at)
            && (is_null($this->expires_at) || $this->expires_at->isFuture());
    }

    public function markAccessed(): void
    {
        if (is_null($this->first_accessed_at)) {
            $this->update(['first_accessed_at' => now()]);
        }
    }
}
