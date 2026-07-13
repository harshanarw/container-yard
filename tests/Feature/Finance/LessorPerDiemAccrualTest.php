<?php

namespace Tests\Feature\Finance;

use App\Models\Container;
use App\Models\Customer;
use App\Models\LessorOnHire;
use App\Services\JobPnlService;
use Tests\Support\FeatureTestCase;

/**
 * Per-diem accrual for lessor on-hire cost: the un-invoiced hire cost builds up
 * daily and shows on the Job P&L / margin report as accrued (WIP) cost, before
 * the actual supplier invoice arrives — kept out of realized margin.
 */
class LessorPerDiemAccrualTest extends FeatureTestCase
{
    private function makeHire(array $attrs = []): LessorOnHire
    {
        return LessorOnHire::create(array_merge([
            'container_id'  => Container::factory()->create()->id,
            'lessor_id'     => Customer::factory()->create()->id,
            'on_hire_date'  => now()->subDays(3)->toDateString(),
            'per_diem_rate' => 10,
            'status'        => 'active',
        ], $attrs));
    }

    public function test_accrual_math_is_inclusive_of_the_on_hire_day(): void
    {
        // Active, on-hired 3 days ago → 4 chargeable days (incl. today) × 10 = 40.
        $active = $this->makeHire();
        $this->assertSame(4, $active->accruedDays());
        $this->assertEqualsWithDelta(40.0, $active->accruedCost(), 0.01);

        // Completed hire stops at its off-hire date (5 → 2 days ago = 4 days × 10).
        $done = $this->makeHire([
            'on_hire_date'  => now()->subDays(5)->toDateString(),
            'off_hire_date' => now()->subDays(2)->toDateString(),
            'status'        => 'completed',
        ]);
        $this->assertSame(4, $done->accruedDays());
        $this->assertEqualsWithDelta(40.0, $done->accruedCost(), 0.01);

        // Cancelled accrues nothing; a missing rate accrues nothing.
        $this->assertSame(0.0, $this->makeHire(['status' => 'cancelled'])->accruedCost());
        $this->assertSame(0.0, $this->makeHire(['per_diem_rate' => null])->accruedCost());
    }

    public function test_on_hire_job_shows_accrued_cost_on_the_pnl_and_report(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $lessor    = Customer::factory()->create();
        $container = Container::factory()->create(['status' => 'in_yard']);

        // On-hire with a per-diem rate → opens a costed job, no GL activity yet.
        $this->post(route('yard.lessor-hires.store'), [
            'container_id'  => $container->id,
            'lessor_id'     => $lessor->id,
            'on_hire_date'  => now()->subDays(2)->toDateString(),
            'per_diem_rate' => 25,
        ])->assertSessionHasNoErrors();

        $hire = LessorOnHire::latest('id')->firstOrFail();
        $job  = $hire->yardJob;

        // Single-job P&L: accrued lessor cost present, realized cost still zero.
        $pnl = app(JobPnlService::class)->compute($job->fresh());
        $this->assertEqualsWithDelta(75.0, $pnl['lessor_accrued'], 0.01); // 3 days × 25
        $this->assertSame(3, $pnl['lessor_accrued_days']);
        $this->assertEqualsWithDelta(0.0, $pnl['realized_cost'], 0.01);
        $this->assertTrue($pnl['has_data']);

        // Cross-job report: the job appears (activity via accrual) with accrued cost,
        // and it is NOT folded into realized margin.
        $summary = app(JobPnlService::class)->summary([]);
        $row = $summary['rows']->firstWhere('job.id', $job->id);
        $this->assertNotNull($row, 'On-hire job with accruing cost should appear in the report.');
        $this->assertEqualsWithDelta(75.0, $row['accrued_cost'], 0.01);
        $this->assertEqualsWithDelta(0.0, $row['realized_margin'], 0.01);
        $this->assertEqualsWithDelta(75.0, $summary['totals']['accrued_cost'], 0.01);
    }
}
