<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use App\Services\ContainerHireService;
use App\Services\ContainerMrStatusService;
use App\Services\LessorOnHireService;
use App\Support\MrStatusCatalogue as Cat;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The renter takes the box away, and brings it back.
 *
 * Three layers sit on one container during a rental, and a gate officer meets
 * all of them at once:
 *
 *   the stay      the shipping line's job — the box arrived under it
 *     the lease   the yard took it on hire from that line (AP, held by the yard)
 *       the let   the yard put it out to a renting customer (AR, held by them)
 *
 * Only the outer and inner layers involve a truck. A lease-in moves nothing, so
 * it records no gate movement; a letting moves the box twice, and those two are
 * ordinary movements that count as such everywhere.
 *
 * Gate-out used to refuse any container with an active hire outright. That was
 * right when a hire was a paper record with no job behind it, and wrong now:
 * the release *is* the thing the letting was opened for, so refusing it left
 * the yard unable to perform the agreement it had just recorded.
 *
 * What the release has to get right is the job on the movement. Stamped with
 * the stay's job it says the shipping line took their own container away, which
 * is the opposite of what happened. Stamped with the letting's, `movement ->
 * job -> holder` names the renter with no column of its own.
 */
class RentalGateFlowTest extends FeatureTestCase
{
    private Customer $line;
    private Customer $renter;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 10:00:00');

        // Before any fixture that stamps created_by, which is NOT NULL on
        // gate_movements and reads auth()->id().
        $this->actingAsSystemAdmin();

        $this->line   = Customer::factory()->create(['name' => 'Maersk Line']);
        $this->renter = Customer::factory()->create(['name' => 'ABC Traders']);

        // The factory leaves equipment_type_id null; the gate-in form requires
        // one, and the return below goes through the real form.
        $eqt = \App\Models\EquipmentType::firstOrFail();

        $this->container = Container::factory()->create([
            'container_no'      => 'RENT0000001',
            'customer_id'       => $this->line->id,
            'equipment_type_id' => $eqt->id,
            'size'              => $eqt->size,
            'type_code'         => $eqt->type_code,
            'status'            => 'in_yard',
            'cargo_status'      => 'empty',
            'condition'         => 'sound',
        ]);

        $this->arrive();
        $this->storage();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The job types the lifecycle needs ───────────────────────────────────

    /**
     * A lease and a letting are agreements, not movements. Offering them as
     * gate purposes invites an operator to record an arrival that never
     * happened — the phantom movement migration 000316 exists to prevent.
     */
    public function test_the_commercial_job_types_are_not_gate_purposes(): void
    {
        foreach (['LESSOR_ONHIRE', 'CONTAINER_RELET'] as $code) {
            $type = YardJobType::where('job_type_code', $code)->firstOrFail();

            $this->assertSame('commercial', $type->movement_direction, $code);
            $this->assertFalse($type->isGatePurpose(), $code);
        }

        $offered = YardJobType::active()->forGateIn()->pluck('job_type_code');

        $this->assertNotContains('LESSOR_ONHIRE', $offered);
        $this->assertNotContains('CONTAINER_RELET', $offered);
    }

    /** The renter bringing the box back needs a purpose of its own to arrive on. */
    public function test_a_hire_return_gate_in_type_exists(): void
    {
        $type = YardJobType::where('job_type_code', 'HIRE_RETURN_IN')->firstOrFail();

        $this->assertSame('gate_in', $type->movement_direction);
        $this->assertTrue($type->is_active);
        $this->assertTrue((bool) $type->survey_applicable,
            'Damage during a hire is the renter\'s, so the return is inspected.');
    }

    // ── The release ─────────────────────────────────────────────────────────

    public function test_a_rented_container_can_be_gated_out(): void
    {
        $this->lease();
        $this->reLet();

        $this->release()->assertRedirect();

        $this->assertSame('released', $this->container->fresh()->status);
    }

    /**
     * The whole mechanism: the departure carries the letting's job, so the
     * renting party is reachable without a column of its own.
     */
    public function test_the_departure_carries_the_rent_job_and_names_the_renter(): void
    {
        $this->lease();
        $hire = $this->reLet();

        $this->release();

        $out = $this->departure();

        $this->assertSame($hire->yard_job_id, $out->yard_job_id);
        $this->assertSame('ABC Traders', $out->yardJob->holder()->name);
    }

    /** The purpose follows from the rental status; nobody is asked for it. */
    public function test_the_release_purpose_is_set_from_the_rental_status(): void
    {
        $this->lease();
        $this->reLet();

        $this->release(['gate_out_purpose' => '']);

        $this->assertSame('ONHIRE_OUT', $this->departure()->gate_out_purpose);
    }

    /**
     * The stay is not lost by stamping the letting: the letting's job is a
     * sub-job of the lease, whose own parent is the stay. All three are one
     * walk up `parent_job_id` from the movement.
     */
    public function test_all_three_jobs_are_reachable_from_the_departure(): void
    {
        $lease = $this->lease();
        $this->reLet();
        $this->release();

        $rentJob = $this->departure()->yardJob;

        $this->assertSame($lease->yard_job_id, $rentJob->parent_job_id);
        $this->assertSame($this->stayJob()->id, $rentJob->parentJob->parent_job_id);
    }

    /** An ordinary release is untouched — no letting, no change. */
    public function test_a_container_with_no_letting_still_leaves_on_the_stay_job(): void
    {
        $this->release();

        $this->assertSame($this->stayJob()->id, $this->departure()->yard_job_id);
    }

    /**
     * An internal letting has no counterparty, so ContainerHireService opens no
     * job for it — `yard_jobs.customer_id` is not nullable and inventing a
     * party would put a fictional name on a P&L. There is nobody to release the
     * box to, so this one still blocks.
     */
    public function test_an_internal_letting_still_blocks_the_release(): void
    {
        $this->lease();
        app(ContainerHireService::class)->onHire(
            $this->container->fresh(),
            ['on_hire_date' => '2026-09-25', 'hire_customer_id' => null],
            auth()->id(),
        );

        $this->release()->assertSessionHasErrors('container_no');

        $this->assertSame('in_yard', $this->container->fresh()->status);
    }

    /** Storage stays suspended while the box is out: the lease is still running. */
    public function test_the_release_does_not_resume_the_lines_storage(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        $this->assertSame(
            0,
            YardStorage::where('container_id', $this->container->id)
                ->whereIn('hire_type', ['normal', 'resumed'])
                ->whereNull('gate_out_date')
                ->count(),
            'The yard cannot bill the line for a box it is paying that line rent on.',
        );
    }

    // ── The gate form ───────────────────────────────────────────────────────

    public function test_the_lookup_names_the_renter_and_the_job_chain(): void
    {
        $lease = $this->lease();
        $hire  = $this->reLet();

        $data = $this->lookup();

        $this->assertNull($data['release_block'], 'A rental release is context, not an obstacle.');
        $this->assertSame('ABC Traders', $data['hire_release']['renter']);
        $this->assertSame('ONHIRE_OUT', $data['hire_release']['suggested_purpose']);
        $this->assertSame($hire->yardJob->job_no, $data['hire_release']['rent_job_no']);
        $this->assertSame($lease->yardJob->job_no, $data['hire_release']['lease_job_no']);

        $this->assertSame(
            ['Gate-in job', 'On-hire (lease) job', 'Rent job'],
            array_column($data['hire_release']['job_chain'], 'label'),
        );
    }

    /** On hire from the line, but sitting on the ground between lettings. */
    public function test_the_lookup_reports_a_lease_with_no_letting(): void
    {
        $this->lease();

        $data = $this->lookup();

        $this->assertNull($data['hire_release']);
        $this->assertSame('Maersk Line', $data['lease_only']['lessor']);
    }

    public function test_the_lookup_blocks_an_internal_letting(): void
    {
        $this->lease();
        app(ContainerHireService::class)->onHire(
            $this->container->fresh(),
            ['on_hire_date' => '2026-09-25', 'hire_customer_id' => null],
            auth()->id(),
        );

        $this->assertStringContainsString('internal hire', $this->lookup()['release_block']);
    }

    // ── The return ──────────────────────────────────────────────────────────

    public function test_the_return_arrives_on_the_rent_job_and_closes_the_letting(): void
    {
        $lease = $this->lease();
        $hire  = $this->reLet();
        $this->release();

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->returnBox()->assertRedirect();

        $arrival = GateMovement::where('container_id', $this->container->id)
            ->where('movement_type', 'in')->latest('gate_in_time')->firstOrFail();

        $this->assertSame($hire->yard_job_id, $arrival->yard_job_id);
        $this->assertSame('HIRE_RETURN_IN', $arrival->job_type_code);

        $this->assertSame('completed', $hire->fresh()->status);
        $this->assertSame('active', $lease->fresh()->status, 'The lease outlives the letting.');
    }

    /**
     * The arrival must not open a billable storage row. Storage is suspended —
     * by the lease, which left a zero-rated `lease_in` row — and a `normal` row
     * here would start charging the line again for a box the yard is still
     * paying them rent on.
     */
    public function test_the_return_opens_no_storage_while_the_lease_runs(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->returnBox();

        $this->assertSame(
            0,
            YardStorage::where('container_id', $this->container->id)
                ->whereIn('hire_type', ['normal', 'resumed'])
                ->whereNull('gate_out_date')
                ->count(),
        );
    }

    /** One lease, many lettings — the box goes out again after coming back. */
    public function test_the_box_can_be_let_again_after_it_returns(): void
    {
        $lease = $this->lease();
        $this->reLet();
        $this->release();

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->returnBox();

        Carbon::setTestNow('2026-10-10 10:00:00');
        $second = app(ContainerHireService::class)->onHire(
            $this->container->fresh(),
            ['on_hire_date' => '2026-10-10', 'hire_customer_id' => $this->renter->id],
            auth()->id(),
        );

        $this->release();

        $this->assertCount(2, $lease->fresh()->reLets);
        $this->assertSame($second->yard_job_id, $this->departure()->yard_job_id);
    }

    // ── Where the container is, and whose it is ─────────────────────────────

    /**
     * "Is it on your ground?" and "is it still with you?" are two questions,
     * and during a rental they have different answers. The gate-out closes the
     * cycle, so the status ladder used to read "Gated out" — dropping the box
     * off the line's statement mid-lease, while the yard was still paying rent
     * on it.
     */
    public function test_a_rented_out_container_still_reads_on_hire_after_it_leaves(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        app(ContainerMrStatusService::class)->resolveAndSync($this->container->fresh());

        $this->assertSame(Cat::LEASED_IN_RENTED_OUT, $this->container->fresh()->mr_status);
        $this->assertSame('released', $this->container->fresh()->status,
            'Outside the yard, and still the yard\'s responsibility.');
    }

    /** Once the agreements end, an ordinary departure reads as one again. */
    public function test_an_ordinary_departure_still_reads_gated_out(): void
    {
        $this->release();

        app(ContainerMrStatusService::class)->resolveAndSync($this->container->fresh());

        $this->assertSame(Cat::GATED_OUT, $this->container->fresh()->mr_status);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function lease(): LessorOnHire
    {
        return app(LessorOnHireService::class)->onHireInYard(
            $this->container->fresh(),
            ['lessor_id' => $this->line->id, 'on_hire_date' => '2026-09-21'],
            auth()->id(),
        );
    }

    private function reLet(string $on = '2026-09-25'): ContainerHire
    {
        Carbon::setTestNow($on . ' 10:00:00');

        return app(ContainerHireService::class)->onHire(
            $this->container->fresh(),
            ['on_hire_date' => $on, 'hire_customer_id' => $this->renter->id],
            auth()->id(),
        );
    }

    private function release(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('yard.gate.out'), array_merge([
            'container_no'  => $this->container->container_no,
            'vehicle_plate' => 'WXY-1234',
            'driver_name'   => 'D Perera',
            'driver_ic'     => '901234567V',
        ], $extra));
    }

    private function returnBox(array $extra = []): \Illuminate\Testing\TestResponse
    {
        // The operator's choice is deliberately wrong here: the job type is
        // settled from the rental status, not from the form.
        $wrong = YardJobType::where('job_type_code', 'EMPTY_RETURN')->firstOrFail();

        return $this->post(route('yard.gate.in'), array_merge([
            'job_type_id'       => $wrong->id,
            'return_reason'     => 'agent_return',
            'container_no'      => $this->container->container_no,
            'equipment_type_id' => $this->container->equipment_type_id,
            'customer_id'       => $this->line->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'WXY-1234',
        ], $extra));
    }

    /** @return array<string,mixed> */
    private function lookup(): array
    {
        return $this->get(route('yard.container-lookup', ['container_no' => $this->container->container_no]))
            ->assertOk()
            ->json();
    }

    private function departure(): GateMovement
    {
        return GateMovement::with('yardJob')
            ->where('container_id', $this->container->id)
            ->where('movement_type', 'out')
            ->latest('gate_out_time')
            ->firstOrFail();
    }

    private function stayJob(): YardJob
    {
        return GateMovement::where('container_id', $this->container->id)
            ->where('movement_type', 'in')
            ->oldest('gate_in_time')
            ->firstOrFail()
            ->yardJob;
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
            'started_at'      => '2026-09-01 08:00:00',
            'created_by'      => auth()->id(),
        ]);

        return GateMovement::create([
            'container_id'    => $this->container->id,
            'container_no'    => $this->container->container_no,
            'customer_id'     => $this->line->id,
            'yard_job_id'     => $job->id,
            'movement_type'   => 'in',
            'size'            => $this->container->size,
            'container_type'  => $this->container->type_code,
            'condition'       => 'sound',
            'cargo_status'    => 'empty',
            'gate_in_time'    => '2026-09-01 08:00:00',
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);
    }

    private function storage(): YardStorage
    {
        return YardStorage::create([
            'container_id'  => $this->container->id,
            'customer_id'   => $this->line->id,
            'gate_in_date'  => '2026-09-01',
            'gate_out_date' => null,
            'free_days'     => 5,
            'daily_rate'    => 750,
            'hire_type'     => 'normal',
        ]);
    }
}
