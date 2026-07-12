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
