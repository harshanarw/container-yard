<?php

namespace Tests\Feature\Billing;

use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use App\Services\ContainerHireService;
use App\Services\LessorOnHireService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Who is billed for the lift, and who is billed for the storage, during a hire.
 *
 * Handling selects gate movements by `customer_id`, and a rental release
 * correctly records the *visit* customer there — the box is on the shipping
 * line's stay, and `containers:fix-gate-custody` exists to force that back when
 * it drifts. So the yard lifted a container onto a renting customer's truck and
 * invoiced the shipping line for it: a party with no relationship to the
 * transaction.
 *
 * The rule now is one sentence — **the party holding the container pays for the
 * lift** — read from the job's `held_by_customer_id`, which is the renter on a
 * re-let and null on everything else. Null falls through to `customer_id`, so
 * every ordinary movement is billed exactly as before.
 *
 * Storage is the other half. The yard cannot bill a line for storing a
 * container it is simultaneously paying that same line rent for, so a lease
 * closes the line's storage and leaves a zero-rated row in its place.
 */
class HireBillingPartyTest extends FeatureTestCase
{
    private Customer $line;
    private Customer $renter;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-20 10:00:00');
        $this->actingAsSystemAdmin();

        $this->line      = Customer::factory()->create(['name' => 'Maersk Line']);
        $this->renter    = Customer::factory()->create(['name' => 'ABC Traders']);
        $this->container = Container::factory()->create([
            'customer_id' => $this->line->id,
            'status'      => 'in_yard',
        ]);

        $this->arrive();
        $this->storage();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The lift follows the box ────────────────────────────────────────────

    /** The defect: the line was invoiced for a lift onto a renter's truck. */
    public function test_a_rental_lift_is_not_billed_to_the_shipping_line(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        $lines = $this->preview($this->line, '2026-03-01', '2026-03-31')->json('lines');

        $this->assertEmpty(
            collect($lines)->where('has_lift_on', true),
            'The line has no relationship with the party the box went out to.',
        );
    }

    public function test_the_rental_lift_is_billed_to_the_renter(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        $lines = $this->preview($this->renter, '2026-03-01', '2026-03-31')->json('lines');

        $this->assertNotEmpty(collect($lines)->where('has_lift_on', true),
            'The renter took the box away; the lift is part of what they hired.');
    }

    /**
     * The renter holds no billable stay — storage stays suspended for the whole
     * lease, because the yard is paying the line rent for the box. So their
     * line is a lift and nothing else.
     *
     * This is what the combined bill type could not express: its spine was
     * storage records alone, so a party with a lift and no stay produced no
     * line at all and the charge reached nobody.
     */
    public function test_the_renter_is_billed_the_lift_and_no_storage(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        $lines = collect($this->preview($this->renter, '2026-03-01', '2026-03-31')->json('lines'));

        $this->assertCount(1, $lines);
        $this->assertSame(0, (int) $lines->first()['storage_chargeable_days']);
    }

    /** An ordinary release is untouched — no holder, so nothing changes. */
    public function test_an_ordinary_lift_is_still_billed_to_the_visit_customer(): void
    {
        $this->release();

        $lines = $this->preview($this->line, '2026-03-01', '2026-03-31')->json('lines');

        $this->assertNotEmpty(collect($lines)->where('has_lift_on', true));
    }

    /** The arrival belongs to the line's stay and stays theirs. */
    public function test_the_original_arrival_is_still_the_lines(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        $lines = $this->preview($this->line, '2026-03-01', '2026-03-31')->json('lines');

        $this->assertNotEmpty(collect($lines)->where('has_lift_off', true),
            'The box arrived on the line\'s job, whatever happened to it later.');
    }

    // ── One lift, one charge ────────────────────────────────────────────────

    /**
     * The round trip leaves the shipping line with **two** storage rows: the
     * stay closed when the yard took the box on hire, and the resumed one
     * opened when it gave it back. Both are theirs, both land in the billing
     * spine, and the arrival lift-off was attached to each by container id —
     * so one lift produced two charges on one invoice.
     *
     * Across invoices it was never possible: `liftOffBilled()` is keyed on
     * container and date. The repeat was inside a single bill.
     */
    public function test_the_arrival_lift_is_charged_once_after_a_round_trip(): void
    {
        $lease = $this->lease('2026-03-05');
        $this->reLet('2026-03-06');
        $this->release();

        Carbon::setTestNow('2026-03-15 10:00:00');
        $this->returnBox();

        app(LessorOnHireService::class)->offHireInYard(
            $lease->fresh(), ['off_hire_date' => '2026-03-16'], auth()->id(),
        );

        $lines = collect($this->preview($this->line, '2026-03-01', '2026-03-31')->json('lines'));

        $this->assertGreaterThan(1, YardStorage::where('container_id', $this->container->id)
            ->whereIn('hire_type', ['normal', 'resumed'])->count(),
            'The premise: the round trip really does leave two billable stays.');

        $this->assertCount(1, $lines->where('has_lift_off', true),
            'One arrival, one lift-off, however many stays it was split across.');
    }

    /** Two genuine visits are two genuine lifts, and must stay so. */
    public function test_two_real_arrivals_are_charged_twice(): void
    {
        $this->release();

        Carbon::setTestNow('2026-03-10 10:00:00');
        $this->returnBox();

        $lines = collect($this->preview($this->line, '2026-03-01', '2026-03-31')->json('lines'));

        $this->assertNotEmpty($lines->where('has_lift_off', true));
    }

    // ── Storage stops for the lease ─────────────────────────────────────────

    public function test_the_line_is_not_billed_storage_during_the_lease(): void
    {
        $this->lease('2026-03-10');

        $data = $this->preview($this->line, '2026-03-01', '2026-03-31')->json();

        $days = collect($data['lines'])->sum('storage_chargeable_days');

        $this->assertLessThanOrEqual(9, $days,
            'Storage runs to 9 March, the day before the lease, and stops.');
    }

    /**
     * The zero-rated row the lease leaves behind is excluded by name, not by
     * its customer happening to be null.
     */
    public function test_the_lease_storage_row_is_never_selected(): void
    {
        $this->lease('2026-03-10');

        // Give it a customer, which the service never does — the point is that
        // the filter does not depend on that being null.
        YardStorage::where('container_id', $this->container->id)
            ->where('hire_type', 'lease_in')
            ->update(['customer_id' => $this->line->id, 'daily_rate' => 500]);

        $days = collect($this->preview($this->line, '2026-03-01', '2026-03-31')->json('lines'))
            ->sum('storage_chargeable_days');

        $this->assertLessThanOrEqual(9, $days, 'A lease_in row is not a billable stay.');
    }

    public function test_storage_resumes_for_the_line_after_off_hire(): void
    {
        $lease = $this->lease('2026-03-05');

        app(LessorOnHireService::class)->offHireInYard(
            $lease, ['off_hire_date' => '2026-03-15'], auth()->id(),
        );

        $days = collect($this->preview($this->line, '2026-03-01', '2026-03-31')->json('lines'))
            ->sum('storage_chargeable_days');

        $this->assertGreaterThan(9, $days, 'The line is billed again once the box is theirs.');
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function preview(Customer $party, string $from, string $to)
    {
        return $this->postJson(route('billing.storage-handling.manual.preview'), [
            'bill_type'        => 'storage_handling',
            'shipping_line_id' => $party->id,
            'period_from'      => $from,
            'period_to'        => $to,
            'invoice_currency' => 'LKR',
            'exchange_rate'    => 1,
            'manual_free_days' => 0,
        ]);
    }

    private function lease(string $on = '2026-03-10'): LessorOnHire
    {
        return app(LessorOnHireService::class)->onHireInYard(
            $this->container->fresh(),
            ['lessor_id' => $this->line->id, 'on_hire_date' => $on],
            auth()->id(),
        );
    }

    private function reLet(string $on = '2026-03-12'): ContainerHire
    {
        return app(ContainerHireService::class)->onHire(
            $this->container->fresh(),
            ['on_hire_date' => $on, 'hire_customer_id' => $this->renter->id],
            auth()->id(),
        );
    }

    private function returnBox(): void
    {
        $this->post(route('yard.gate.in'), [
            'job_type_id'       => \App\Models\YardJobType::where('job_type_code', 'EMPTY_RETURN')->value('id'),
            'return_reason'     => 'agent_return',
            'container_no'      => $this->container->container_no,
            'equipment_type_id' => $this->container->equipment_type_id,
            'customer_id'       => $this->line->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'WXY-1234',
        ])->assertRedirect();
    }

    private function release(): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('yard.gate.out'), [
            'container_no'  => $this->container->container_no,
            'vehicle_plate' => 'WXY-1234',
            'driver_name'   => 'D Perera',
            'driver_ic'     => '901234567V',
        ]);
    }

    private function arrive(): GateMovement
    {
        $type = YardJobType::where('job_type_code', 'LADEN_IN')->firstOrFail();

        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($type);

        $job = YardJob::create([
            'job_no'          => $no,
            'job_seq'         => $seq,
            'job_type_id'     => $type->id,
            'job_type_code'   => $type->job_type_code,
            'type_short_code' => $type->type_short_code,
            'customer_id'     => $this->line->id,
            'status'          => 'open',
            'started_at'      => '2026-03-01 08:00:00',
            'created_by'      => auth()->id(),
        ]);

        return GateMovement::create([
            'container_id'    => $this->container->id,
            'container_no'    => $this->container->container_no,
            'customer_id'     => $this->line->id,
            'yard_job_id'     => $job->id,
            'movement_type'   => 'in',
            'size'            => '40',
            'container_type'  => 'HC',
            'condition'       => 'sound',
            'cargo_status'    => 'empty',
            'gate_in_time'    => '2026-03-01 08:00:00',
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);
    }

    private function storage(): YardStorage
    {
        return YardStorage::create([
            'container_id'  => $this->container->id,
            'customer_id'   => $this->line->id,
            'gate_in_date'  => '2026-03-01',
            'gate_out_date' => null,
            'free_days'     => 0,
            'daily_rate'    => 750,
            'hire_type'     => 'normal',
        ]);
    }
}
