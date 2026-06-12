<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')->withTimestamps();
    }

    // ── Permission assignment helpers ─────────────────────────────────────────

    public function givePermissionTo(string ...$permNames): void
    {
        $ids = Permission::whereIn('name', $permNames)->pluck('id');
        $this->permissions()->syncWithoutDetaching($ids);
        Cache::forget('_gate_permission_names');
    }

    public function revokePermissionTo(string ...$permNames): void
    {
        $ids = Permission::whereIn('name', $permNames)->pluck('id');
        $this->permissions()->detach($ids);
    }

    public function syncPermissions(array $permNames): void
    {
        $ids = Permission::whereIn('name', $permNames)->pluck('id');
        $this->permissions()->sync($ids);
        Cache::forget('_gate_permission_names');
    }

    public function hasPermission(string $name): bool
    {
        return $this->permissions()->where('name', $name)->exists();
    }
}
