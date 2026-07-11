<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base class for feature / regression tests.
 *
 * Runs against the MySQL "testing" database (see tests/README.md): the schema
 * is migrated fresh and the full DatabaseSeeder is run once per test process to
 * establish a realistic baseline (master data, roles, permissions, sample
 * customers/containers). Each test then runs inside a transaction that rolls
 * back, so tests stay isolated.
 */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    /** Seed the full baseline once after the fresh migration. */
    protected $seed = true;

    /** Log in as a super-user (bypasses RBAC) — for broad smoke coverage. */
    protected function actingAsSystemAdmin(): User
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        return $admin;
    }

    /** Log in as a user whose primary role is $role (RBAC applies). */
    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }
}
