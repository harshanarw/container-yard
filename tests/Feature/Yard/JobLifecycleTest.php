<?php

namespace Tests\Feature\Yard;

use App\Exceptions\JobLifecycleException;
use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use App\Services\ContainerHireService;
use App\Services\JobLifecycleService;
use App\Services\LessorOnHireService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * The job life cycle (Rule 5; Phase 2a).
 *
 * Gate-in and its job are one record; the final gate-out completes the main
 * job, or leaves it waiting on open sub jobs; closing the last sub job
 * completes it; a job is never completed over open sub jobs; reopening is
 * refused once billed; sub jobs are numbered under their main job.
 */
class JobLifecycleTest extends FeatureTestCase
{
    private const BOX = 'LIFU1234565';

    private Customer $line;
    private Customer $renter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->actingAsSystemAdmin();

        $this->line   = Customer::factory()->create(['name' => 'Maersk Line']);
        $this->renter = Customer::factory()->create(['name' => 'ABC Traders']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function gateInBox(): GateMovement
    {
        $type = YardJobType::where('job_type_code', 'STORAGE_IN')->first()
            ?? YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
                ->where('job_type_code', '!=', 'EMPTY_RETURN')->firstOrFail();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $type->id,
            'container_no'      => self::BOX,
            'equipment_type_id' => (EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::firstOrFail())->id,
            'customer_id'       => $this->line->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
        ])->assertSessionHasNoErrors();

        return GateMovement::where('container_no', self::BOX)->where('movement_type', 'in')->latest('id')->firstOrFail();
    }

    private function gateOutBox(): void
    {
        $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'  => self::BOX,
            'vehicle_plate' => 'OUT1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
        ])->assertSessionHasNoErrors();
    }

    private function box(): Container
    {
        return Container::where('container_no', self::BOX)->firstOrFail();
    }

    private function lease(): LessorOnHire
    {
        return app(LessorOnHireService::class)->onHireInYard(
            $this->box(),
            ['lessor_id' => $this->line->id, 'on_hire_date' => '2026-09-21'],
            auth()->id(),
        );
    }

    private function reLet(string $on = '2026-09-25'): ContainerHire
    {
        return app(ContainerHireService::class)->onHire(
            $this->box(),
            ['on_hire_date' => $on, 'hire_customer_id' => $this->renter->id],
            auth()->id(),
        );
    }

    // ── Gate-in: one record (2.1) ─────────────────────────────────────────────

    public function test_gate_in_saves_the_job_and_links_the_stay_to_it(): void
    {
        $arrival = $this->gateInBox();

        $this->assertNotNull($arrival->yard_job_id);
        $this->assertSame(
            $arrival->yard_job_id,
            YardStorage::where('gate_movement_id', $arrival->id)->value('yard_job_id'),
            'The stay should belong to the visit job.'
        );
    }

    public function test_gate_in_saves_nothing_when_the_job_cannot_be_created(): void
    {
        YardJob::creating(fn () => throw new \RuntimeException('job numbering is down'));

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => (YardJobType::where('job_type_code', 'STORAGE_IN')->first()
                ?? YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
                    ->where('job_type_code', '!=', 'EMPTY_RETURN')->firstOrFail())->id,
            'container_no'      => self::BOX,
            'equipment_type_id' => EquipmentType::firstOrFail()->id,
            'customer_id'       => $this->line->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
        ])->assertSessionHasErrors('container_no');

        $this->assertDatabaseMissing('gate_movements', ['container_no' => self::BOX]);
        $this->assertDatabaseMissing('containers', ['container_no' => self::BOX]);
    }

    // ── Final gate-out (5.2) ──────────────────────────────────────────────────

    public function test_the_final_gate_out_completes_the_main_job(): void
    {
        $job = $this->gateInBox()->yardJob;
        $this->assertSame('open', $job->status);

        $this->gateOutBox();

        $job->refresh();
        $this->assertSame(YardJob::STATUS_COMPLETED, $job->status);
        $this->assertStringStartsWith('Final gate-out ', $job->closed_reason);
        $this->assertNotNull($job->completed_at);
    }

    public function test_the_job_screen_shows_the_departure_and_its_time(): void
    {
        $job = $this->gateInBox()->yardJob;
        Carbon::setTestNow('2026-09-23 15:45:00');
        $this->gateOutBox();

        $out = GateMovement::where('container_no', self::BOX)->where('movement_type', 'out')->firstOrFail();
        $this->assertNotNull($out->gate_out_time, 'The premise: the departure has a gate-out time.');

        $this->get(route('yard.jobs.show', $job))
            ->assertOk()
            ->assertSeeInOrder(['Gate In', '20 Sep 2026 10:00', 'Gate Out', $out->eir_no, $out->gate_out_time->format('d M Y H:i')]);
    }

    public function test_the_main_job_lists_its_sub_jobs_and_a_sub_job_names_its_main_job(): void
    {
        $job     = $this->gateInBox()->yardJob;
        $lease   = $this->lease();
        $letting = $this->reLet();

        // The main job: the whole tree, in order, with each sub job's status.
        $this->get(route('yard.jobs.show', $job))
            ->assertOk()
            ->assertSee('Main Job &amp; Sub Jobs', false)
            ->assertSee('2 open')
            ->assertSeeInOrder([$job->job_no, $lease->yardJob->job_no, $letting->yardJob->job_no]);

        // A sub job: which job it belongs to, and the same tree.
        $this->get(route('yard.jobs.show', $letting->yardJob))
            ->assertOk()
            ->assertSeeInOrder(['Sub job', 'of', $lease->yardJob->job_no])
            ->assertSee('(this job)');
    }

    public function test_a_job_with_no_sub_jobs_shows_no_tree(): void
    {
        $job = $this->gateInBox()->yardJob;

        $this->get(route('yard.jobs.show', $job))
            ->assertOk()
            ->assertDontSee('Main Job &amp; Sub Jobs', false)
            ->assertDontSee('Sub job');
    }

    public function test_a_gate_out_with_an_open_lease_leaves_the_job_waiting_and_closing_it_completes(): void
    {
        $job   = $this->gateInBox()->yardJob;
        $lease = $this->lease();

        $lookup = $this->getJson(route('yard.container-lookup', ['container_no' => self::BOX]))->json();
        $this->assertSame([$lease->yardJob->job_no], array_column($lookup['open_sub_jobs'], 'job_no'));

        $this->gateOutBox();
        $this->assertSame(YardJob::STATUS_AWAITING, $job->fresh()->status);

        app(JobLifecycleService::class)->complete($lease->yardJob->fresh(), auth()->id(), 'Lease off-hired');

        $job->refresh();
        $this->assertSame(YardJob::STATUS_COMPLETED, $job->status);
        $this->assertStringStartsWith('Last sub job closed', $job->closed_reason);
    }

    public function test_a_rental_departure_does_not_complete_the_stay(): void
    {
        $job = $this->gateInBox()->yardJob;
        $this->lease();
        $this->reLet();

        $this->gateOutBox(); // leaves with the renter

        $this->assertTrue($job->fresh()->isActive());
    }

    public function test_deleting_the_final_gate_out_reopens_the_job(): void
    {
        $job = $this->gateInBox()->yardJob;
        $this->gateOutBox();
        $this->assertSame(YardJob::STATUS_COMPLETED, $job->fresh()->status);

        $out = GateMovement::where('container_no', self::BOX)->where('movement_type', 'out')->firstOrFail();
        $this->delete(route('yard.movements.destroy', $out));

        $this->assertSame(YardJob::STATUS_OPEN, $job->fresh()->status);
        $this->assertNull($job->fresh()->completed_at);
    }

    // ── No completing over open sub jobs (5.1) ────────────────────────────────

    public function test_a_main_job_cannot_be_completed_while_a_sub_job_is_open(): void
    {
        $job = $this->gateInBox()->yardJob;
        $this->lease();

        $this->from(route('yard.jobs.show', $job))
            ->patch(route('yard.jobs.update', $job), ['status' => 'completed'])
            ->assertSessionHasErrors('status');

        $this->assertSame('open', $job->fresh()->status);
    }

    public function test_a_lease_cannot_be_off_hired_while_its_letting_is_open(): void
    {
        $this->gateInBox();
        $lease   = $this->lease();
        $letting = $this->reLet();

        try {
            app(LessorOnHireService::class)->offHireInYard($lease->fresh(), ['off_hire_date' => '2026-09-26'], auth()->id());
            $this->fail('The lease was off-hired over an open letting.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($letting->yardJob->job_no, $e->getMessage());
        }

        $this->assertSame('active', $lease->fresh()->status);
        $this->assertTrue($lease->yardJob->fresh()->isActive());
    }

    public function test_a_lease_on_a_box_that_has_left_off_hires_on_or_before_the_departure(): void
    {
        $job   = $this->gateInBox()->yardJob;
        $lease = $this->lease(); // on hire 21 Sep

        Carbon::setTestNow('2026-09-24 14:30:00');
        $this->gateOutBox();
        $this->assertSame(YardJob::STATUS_AWAITING, $job->fresh()->status, 'The premise: the box left with the lease open.');

        $service = app(LessorOnHireService::class);

        // After the departure: refused, nothing written.
        try {
            $service->offHireInYard($lease->fresh(), ['off_hire_date' => '2026-09-25'], auth()->id());
            $this->fail('The lease was off-hired after the box had left.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('on or before', $e->getMessage());
        }
        $this->assertSame('active', $lease->fresh()->status);

        // On the departure day: allowed, and the line's storage ends with the box.
        $service->offHireInYard($lease->fresh(), ['off_hire_date' => '2026-09-24'], auth()->id());

        $this->assertSame('completed', $lease->fresh()->status);
        $this->assertSame(0, YardStorage::where('container_id', $this->box()->id)->whereNull('gate_out_date')->count(),
            'A storage row was left open for a box that has gone.');
        $resumed = YardStorage::where('container_id', $this->box()->id)->where('hire_type', 'resumed')->firstOrFail();
        $this->assertSame('2026-09-24', $resumed->gate_out_date->toDateString());
        $this->assertSame(YardJob::STATUS_COMPLETED, $job->fresh()->status);
    }

    // ── Reopen (5.3) ──────────────────────────────────────────────────────────

    public function test_a_completed_job_can_be_reopened_until_it_is_billed(): void
    {
        $job = $this->gateInBox()->yardJob;
        $lifecycle = app(JobLifecycleService::class);
        $lifecycle->complete($job, auth()->id(), 'test');

        $lifecycle->reopen($job->fresh(), auth()->id());
        $this->assertSame('open', $job->fresh()->status);

        $lifecycle->complete($job->fresh(), auth()->id(), 'test');
        $invoiceId = DB::table('general_invoices')->insertGetId([
            'invoice_no' => 'GI-LIFE-1', 'customer_id' => $this->line->id,
            'invoice_date' => '2026-09-20', 'status' => 'issued',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('general_invoice_lines')->insert([
            'general_invoice_id' => $invoiceId, 'description' => 'Handling', 'yard_job_id' => $job->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(JobLifecycleException::class);
        $lifecycle->reopen($job->fresh(), auth()->id());
    }

    public function test_completing_needs_its_own_permission(): void
    {
        $job = $this->gateInBox()->yardJob;

        $role = \App\Models\Role::create(['name' => 'job_editor_test', 'display_name' => 'Job editor (test)']);
        $role->syncPermissions(['yard.jobs.view', 'yard.jobs.edit']);
        $this->actingAsRole('job_editor_test');

        $this->from(route('yard.jobs.show', $job))
            ->patch(route('yard.jobs.update', $job), ['status' => 'completed'])
            ->assertForbidden();

        $this->assertSame('open', $job->fresh()->status);
    }

    // ── Numbering (D20) ───────────────────────────────────────────────────────

    public function test_sub_jobs_are_numbered_under_their_main_job(): void
    {
        $stayNo = $this->gateInBox()->yardJob->job_no;

        $lease  = $this->lease();
        $first  = $this->reLet('2026-09-25');
        app(ContainerHireService::class)->offHire($first, ['off_hire_date' => '2026-09-27'], auth()->id());
        $second = $this->reLet('2026-09-28');

        $this->assertSame("{$stayNo}/LH1", $lease->yardJob->job_no);
        $this->assertSame("{$stayNo}/RL1", $first->yardJob->job_no);
        $this->assertSame("{$stayNo}/RL2", $second->yardJob->job_no);
    }

    public function test_cancelling_a_rental_cancels_its_job(): void
    {
        $this->gateInBox();
        $this->lease();
        $hire = $this->reLet();

        app(ContainerHireService::class)->cancelHire($hire, auth()->id());

        $this->assertSame(YardJob::STATUS_CANCELLED, $hire->yardJob->fresh()->status);
    }

    // ── Backfill ──────────────────────────────────────────────────────────────

    public function test_the_backfill_reports_then_completes_an_ended_visit(): void
    {
        $job = $this->gateInBox()->yardJob;

        // A departure recorded before the life cycle existed: the job stayed open.
        GateMovement::create([
            'container_id' => $this->box()->id, 'container_no' => self::BOX,
            'size' => $this->box()->size, 'container_type' => $this->box()->type_code,
            'customer_id' => $this->line->id, 'yard_job_id' => $job->id,
            'movement_type' => 'out', 'eir_no' => 'OLD-OUT-1',
            'gate_out_time' => '2026-09-22 09:00:00', 'created_by' => auth()->id(),
        ]);
        $this->assertSame('open', $job->fresh()->status);

        $this->artisan('jobs:close-ended-visits', ['--container' => self::BOX])
            ->expectsOutputToContain('Report only')
            ->assertSuccessful();
        $this->assertSame('open', $job->fresh()->status, 'The report must not change anything.');

        $this->artisan('jobs:close-ended-visits', ['--container' => self::BOX, '--fix' => true])->assertSuccessful();

        $job->refresh();
        $this->assertSame(YardJob::STATUS_COMPLETED, $job->status);
        $this->assertSame('2026-09-22 09:00:00', $job->completed_at->format('Y-m-d H:i:s'));
        $this->assertStringStartsWith('Backfill: visit ended', $job->closed_reason);
    }
}
