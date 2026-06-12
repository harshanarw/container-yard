<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'module',
        'action',
        'display_name',
        'description',
        'sort_order',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions')
                    ->withPivot('granted')
                    ->withTimestamps();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function allGroupedByModule(): array
    {
        return static::orderBy('module')->orderBy('sort_order')->get()
            ->groupBy('module')
            ->toArray();
    }
}
