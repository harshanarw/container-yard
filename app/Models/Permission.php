<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    /**
     * How each action slug reads in the permission list.
     *
     * Both writers — `PermissionSeeder` and `permissions:sync` — build display
     * names from this, and they must agree: whichever one an operator happens to
     * run should produce the same wording. They each kept their own copy until
     * the copies drifted (one gained `manual`, the other `backdate`, and neither
     * had both), so a permission's label depended on which tool created it.
     */
    public const ACTION_LABELS = [
        'view'             => 'View',
        'create'           => 'Create',
        'edit'             => 'Edit',
        'delete'           => 'Delete',
        'approve'          => 'Approve',
        'reject'           => 'Reject',
        'manual'           => 'Price Manually',
        'pdf'              => 'Generate PDF',
        'email'            => 'Send by Email',
        'toggle'           => 'Activate / Deactivate',
        'gate-in'          => 'Record Gate In',
        'gate-out'         => 'Record Gate Out',
        'plug-in'          => 'Record Plug-In',
        'plug-out'         => 'Record Plug-Out',
        'temp-log'         => 'Record Temperature Log',
        'movement-edit'    => 'Edit Movement',
        'movement-delete'  => 'Delete Movement',
        'backdate'         => 'Backdate Date/Time',
    ];

    /** The label for an action slug, falling back to a readable form of the slug. */
    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? ucwords(str_replace('-', ' ', $action));
    }

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
