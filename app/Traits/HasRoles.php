<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait HasRoles
{
    // Per-request cache — refreshed on every new HTTP request automatically
    protected ?array $permissionCache = null;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
                    ->withPivot('granted')
                    ->withTimestamps();
    }

    // ── Core permission check ─────────────────────────────────────────────────

    public function hasPermissionTo(string $permission): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        return $this->getEffectivePermissions()->contains($permission);
    }

    /**
     * Compute the full set of effective permissions for this user.
     * Cached for the lifetime of this object (one HTTP request).
     */
    public function getEffectivePermissions(): Collection
    {
        if ($this->permissionCache !== null) {
            return collect($this->permissionCache);
        }

        // 1. Permissions inherited from all assigned roles
        $roleIds = $this->roles()->pluck('roles.id');
        $rolePerms = $roleIds->isNotEmpty()
            ? Permission::whereHas('roles', fn($q) => $q->whereIn('roles.id', $roleIds))->pluck('name')
            : collect();

        // 2. Direct user overrides
        $direct  = $this->directPermissions()->get();
        $grants  = $direct->filter(fn($p) => (bool) $p->pivot->granted)->pluck('name');
        $revokes = $direct->filter(fn($p) => !(bool) $p->pivot->granted)->pluck('name');

        // Merge: role perms + direct grants, then subtract direct revokes
        $effective = $rolePerms->merge($grants)->diff($revokes)->unique()->values();

        $this->permissionCache = $effective->toArray();

        return $effective;
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(string ...$roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function assignRole(string ...$roleNames): void
    {
        $ids = Role::whereIn('name', $roleNames)->pluck('id');
        $this->roles()->syncWithoutDetaching($ids);
        $this->flushPermissionCache();
    }

    public function removeRole(string ...$roleNames): void
    {
        $ids = Role::whereIn('name', $roleNames)->pluck('id');
        $this->roles()->detach($ids);
        $this->flushPermissionCache();
    }

    public function syncRoles(array $roleNames): void
    {
        $ids = Role::whereIn('name', $roleNames)->pluck('id');
        $this->roles()->sync($ids);
        $this->flushPermissionCache();
    }

    // ── Direct permission override helpers ────────────────────────────────────

    public function givePermissionTo(string ...$permNames): void
    {
        $ids = Permission::whereIn('name', $permNames)->pluck('id');
        $sync = $ids->mapWithKeys(fn($id) => [$id => ['granted' => true]]);
        $this->directPermissions()->syncWithoutDetaching($sync);
        $this->flushPermissionCache();
    }

    public function revokePermissionTo(string ...$permNames): void
    {
        $ids = Permission::whereIn('name', $permNames)->pluck('id');
        $sync = $ids->mapWithKeys(fn($id) => [$id => ['granted' => false]]);
        $this->directPermissions()->syncWithoutDetaching($sync);
        $this->flushPermissionCache();
    }

    public function clearPermissionOverride(string ...$permNames): void
    {
        $ids = Permission::whereIn('name', $permNames)->pluck('id');
        $this->directPermissions()->detach($ids);
        $this->flushPermissionCache();
    }
}
