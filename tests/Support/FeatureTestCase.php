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

    /**
     * RefreshDatabase resets the rows each test but not the cache. CompanySetting
     * memoises the current settings under a single cache key for 3600s, so a model
     * cached by one test (e.g. with enable_guard_post flipped on) would otherwise
     * leak its flags into the next test's fresh baseline. Flush it per test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\CompanySetting::flushCache();
    }

    /** Log in as a super-user (bypasses RBAC) — for broad smoke coverage. */
    protected function actingAsSystemAdmin(): User
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * Log in as a user whose primary role is $role (RBAC applies).
     *
     * The factory's `role()` state sets the `users.role` label column, but
     * permissions are resolved from the `user_roles` pivot — `HasRoles` reads
     * `$this->roles()`, not the column. Without the pivot row the user has a role
     * name and **no permissions at all**, which quietly ruins tests in both
     * directions: "is this forbidden?" passes for the wrong reason, and "can they
     * still do their job?" fails for one.
     *
     * `firstOrFail()` on purpose — a typo'd role name would otherwise reproduce
     * exactly the permission-less user this exists to prevent.
     */
    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->role($role)->create();

        $user->roles()->syncWithoutDetaching([
            \App\Models\Role::where('name', $role)->firstOrFail()->id,
        ]);
        $user->flushPermissionCache();

        $this->actingAs($user);

        return $user;
    }

    /**
     * Ensure an open financial year + accounting period covers today, so GL
     * posting (invoice issue, receipts, etc.) can resolve a period. The seeder
     * does not create one, and PostingEngine::resolvePeriod() requires it.
     */
    protected function openAccountingPeriodForToday(): void
    {
        $start = now()->startOfYear();
        $end   = now()->endOfYear();

        $fy = \App\Models\FinancialYear::firstOrCreate(
            ['code' => 'FY' . now()->year],
            [
                'description' => 'Test FY ' . now()->year,
                'start_date'  => $start->toDateString(),
                'end_date'    => $end->toDateString(),
                'status'      => 'open',
            ]
        );

        \App\Models\AccountingPeriod::firstOrCreate(
            ['financial_year_id' => $fy->id, 'period_no' => 1],
            [
                'name'       => 'Full Year',
                'start_date' => $start->toDateString(),
                'end_date'   => $end->toDateString(),
                'status'     => 'open',
            ]
        );
    }
}
