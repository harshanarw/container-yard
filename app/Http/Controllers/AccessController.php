<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessController extends Controller
{
    // ── Roles ──────────────────────────────────────────────────────────────────

    public function roles()
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('display_name')->get();

        $stats = [
            'roles'       => $roles->count(),
            'permissions' => Permission::count(),
            'users'       => User::where('status', 'active')->count(),
        ];

        return view('access-control.roles.index', compact('roles', 'stats'));
    }

    public function createRole()
    {
        $sections = $this->buildRoleMatrix();
        return view('access-control.roles.create', compact('sections'));
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'display_name' => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string', 'max:500'],
            'permissions'  => ['nullable', 'array'],
            'permissions.*'=> ['string', 'exists:permissions,name'],
        ], [
            'name.regex' => 'Role name may only contain lowercase letters, numbers, and underscores.',
        ]);

        if (Role::where('name', $validated['name'])->exists()) {
            return back()->withInput()->with('error', "A role named '{$validated['name']}' already exists.");
        }

        $role = Role::create([
            'name'         => $validated['name'],
            'display_name' => $validated['display_name'],
            'description'  => $validated['description'] ?? null,
            'is_system'    => false,
        ]);

        if (!empty($validated['permissions'])) {
            $ids = Permission::whereIn('name', $validated['permissions'])->pluck('id');
            $role->permissions()->sync($ids);
        }

        Cache::forget('_gate_permission_names');

        return redirect()->route('access-control.roles.index')
                         ->with('success', "Role '{$role->display_name}' created successfully.");
    }

    public function editRole(Role $role)
    {
        $sections = $this->buildRoleMatrix($role);
        return view('access-control.roles.edit', compact('role', 'sections'));
    }

    public function updateRole(Request $request, Role $role)
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string', 'max:500'],
            'permissions'  => ['nullable', 'array'],
            'permissions.*'=> ['string', 'exists:permissions,name'],
        ]);

        $role->update([
            'display_name' => $validated['display_name'],
            'description'  => $validated['description'] ?? null,
        ]);

        $ids = Permission::whereIn('name', $validated['permissions'] ?? [])->pluck('id');
        $role->permissions()->sync($ids);
        Cache::forget('_gate_permission_names');

        return redirect()->route('access-control.roles.index')
                         ->with('success', "Role '{$role->display_name}' updated.");
    }

    public function destroyRole(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', "'{$role->display_name}' is a system role and cannot be deleted.");
        }

        $role->delete();

        return redirect()->route('access-control.roles.index')
                         ->with('success', "Role '{$role->display_name}' deleted.");
    }

    // ── Users ──────────────────────────────────────────────────────────────────

    public function users(Request $request)
    {
        $users = User::with('roles')
            ->withCount('directPermissions')
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
            )
            ->where('status', 'active')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('access-control.users.index', compact('users'));
    }

    public function userAccess(User $user)
    {
        $allRoles    = Role::withCount('permissions')->orderBy('display_name')->get();
        $userRoleIds = $user->roles()->pluck('roles.id')->toArray();

        // Permissions this user inherits via their current roles
        $inheritedPerms = collect();
        if (!empty($userRoleIds)) {
            $inheritedPerms = Permission::whereHas('roles', fn($q) =>
                $q->whereIn('roles.id', $userRoleIds)
            )->pluck('name');
        }

        // Direct overrides on this user (keyed by permission name → bool)
        $overrides = $user->directPermissions()->get()
            ->mapWithKeys(fn($p) => [$p->name => (bool) $p->pivot->granted]);

        $sections = $this->buildOverrideMatrix($inheritedPerms, $overrides);

        return view('access-control.users.show', compact(
            'user', 'allRoles', 'userRoleIds', 'sections'
        ));
    }

    public function updateUserRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles'   => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user->roles()->sync($validated['roles'] ?? []);
        $user->flushPermissionCache();

        return back()->with('success', "Roles updated for {$user->full_name}.");
    }

    public function updateUserPermissions(Request $request, User $user)
    {
        $request->validate([
            'grants'   => ['nullable', 'array'],
            'grants.*' => ['string', 'exists:permissions,name'],
            'denies'   => ['nullable', 'array'],
            'denies.*' => ['string', 'exists:permissions,name'],
        ]);

        $grantIds = Permission::whereIn('name', $request->input('grants', []))->pluck('id')
            ->mapWithKeys(fn($id) => [$id => ['granted' => true]]);

        $denyIds = Permission::whereIn('name', $request->input('denies', []))->pluck('id')
            ->mapWithKeys(fn($id) => [$id => ['granted' => false]]);

        DB::transaction(function () use ($user, $grantIds, $denyIds) {
            $user->directPermissions()->detach();
            if ($grantIds->isNotEmpty()) {
                $user->directPermissions()->attach($grantIds->toArray());
            }
            if ($denyIds->isNotEmpty()) {
                $user->directPermissions()->attach($denyIds->toArray());
            }
        });

        $user->flushPermissionCache();

        return back()->with('success', "Permission overrides updated for {$user->full_name}.");
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Build the permission matrix for role create/edit.
     * Returns sections → modules → permissions with checked state.
     */
    private function buildRoleMatrix(?Role $role = null): array
    {
        $modules     = config('modules', []);
        $allPerms    = Permission::all()->keyBy('name');
        $rolePermSet = $role
            ? $role->permissions()->pluck('permissions.name')->flip()
            : collect();

        $sections = [];
        foreach ($modules as $moduleKey => $moduleConfig) {
            $section = $moduleConfig['section'];
            $modulePerms = [];

            foreach ($moduleConfig['actions'] as $sortIdx => $action) {
                $permName = "{$moduleKey}.{$action}";
                if (!$allPerms->has($permName)) continue;

                $modulePerms[] = [
                    'name'    => $permName,
                    'action'  => $action,
                    'display' => $this->actionLabel($action),
                    'checked' => $rolePermSet->has($permName),
                ];
            }

            if (empty($modulePerms)) continue;

            $sections[$section][] = [
                'key'   => $moduleKey,
                'label' => $moduleConfig['label'],
                'perms' => $modulePerms,
            ];
        }

        return $sections;
    }

    /**
     * Build the permission matrix for user override screen.
     * Each permission carries its inherited-from-role state and current override.
     */
    private function buildOverrideMatrix($inheritedPerms, $overrides): array
    {
        $modules  = config('modules', []);
        $allPerms = Permission::all()->keyBy('name');
        $sections = [];

        foreach ($modules as $moduleKey => $moduleConfig) {
            $section     = $moduleConfig['section'];
            $modulePerms = [];

            foreach ($moduleConfig['actions'] as $action) {
                $permName = "{$moduleKey}.{$action}";
                if (!$allPerms->has($permName)) continue;

                $override = $overrides->has($permName)
                    ? ($overrides[$permName] ? 'grant' : 'deny')
                    : 'default';

                $modulePerms[] = [
                    'name'      => $permName,
                    'action'    => $action,
                    'display'   => $this->actionLabel($action),
                    'inherited' => $inheritedPerms->contains($permName),
                    'override'  => $override,
                ];
            }

            if (empty($modulePerms)) continue;

            $sections[$section][] = [
                'key'   => $moduleKey,
                'label' => $moduleConfig['label'],
                'perms' => $modulePerms,
            ];
        }

        return $sections;
    }

    private function actionLabel(string $action): string
    {
        $map = [
            'view'            => 'View',
            'create'          => 'Create',
            'edit'            => 'Edit',
            'delete'          => 'Delete',
            'approve'         => 'Approve',
            'reject'          => 'Reject',
            'pdf'             => 'PDF',
            'email'           => 'Email',
            'toggle'          => 'Toggle',
            'gate-in'         => 'Gate-In',
            'gate-out'        => 'Gate-Out',
            'plug-in'         => 'Plug-In',
            'plug-out'        => 'Plug-Out',
            'temp-log'        => 'Temp Log',
            'movement-edit'   => 'Edit Move',
            'movement-delete' => 'Del Move',
        ];

        return $map[$action] ?? ucwords(str_replace('-', ' ', $action));
    }
}
