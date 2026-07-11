<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Tests\Support\FeatureTestCase;

/**
 * Regression cover for the User Management ↔ RBAC linkage: creating a user with
 * a primary role must link that role into the user_roles pivot so the user
 * actually inherits its permissions (Settings → Roles & Permissions).
 */
class UserRoleLinkageTest extends FeatureTestCase
{
    public function test_creating_a_user_links_the_role_and_resolves_permissions(): void
    {
        $this->actingAsSystemAdmin();

        $response = $this->post(route('users.store'), [
            'first_name'            => 'Test',
            'last_name'             => 'Supervisor',
            'username'              => 'test.supervisor',
            'email'                 => 'test.supervisor@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'yard_supervisor',
            'status'                => 'active',
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('username', 'test.supervisor')->firstOrFail();

        // Primary role string persisted…
        $this->assertSame('yard_supervisor', $user->role);
        // …and linked into the RBAC pivot…
        $this->assertTrue(
            $user->roles()->where('name', 'yard_supervisor')->exists(),
            'The primary role was not linked into user_roles.'
        );
        // …so the user inherits that role's permissions.
        $this->assertNotEmpty(
            $user->getEffectivePermissions(),
            'The user resolved no effective permissions from its role.'
        );
    }

    public function test_username_must_be_unique(): void
    {
        $this->actingAsSystemAdmin();
        User::factory()->create(['username' => 'taken.name']);

        $response = $this->from(route('users.create'))->post(route('users.store'), [
            'first_name'            => 'Dup',
            'last_name'             => 'User',
            'username'              => 'taken.name',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'gate_officer',
            'status'                => 'active',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertSame(1, User::where('username', 'taken.name')->count());
    }
}
