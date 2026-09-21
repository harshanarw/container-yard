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
use App\Services\LessorOnHireService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Who the gate pass names when a rented container leaves and comes back.
 *
 * The pass is the document the driver carries and the guard checks, so it has
 * to name the party actually at the barrier. It printed `$movement->customer`
 * — the *visit* customer, which on a rental release is the shipping line whose
 * stay the box is on. The renter, whose driver is holding the pass, appeared
 * nowhere on it.
 *
 * Two fields, two different questions, and keeping both is the point:
 *
 *   Owner / Shipping Line   whose container it is        — always the line
 *   Customer                who is taking delivery       — the renter, on a hire
 *
 * The same rule as the handling charge: the party holding the container. On
 * every ordinary movement the two are the same party and nothing changes.
 */
class GatePassHirePartyTest extends FeatureTestCase
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

    // ── The release ─────────────────────────────────────────────────────────

    public function test_the_pass_names_the_renter_as_the_customer(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        $this->get(route('yard.movements.gate-pass', $this->departure()))
            ->assertOk()
            ->assertSee('ABC Traders');
    }

    /** Whose box it is has not changed, and the pass still says so. */
    public function test_the_pass_still_names_the_line_as_the_owner(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        $this->get(route('yard.movements.gate-pass', $this->departure()))
            ->assertOk()
            ->assertSee('Owner / Shipping Line', false)
            ->assertSee('Maersk Line');
    }

    /** So the guard can see why the two parties differ rather than query it. */
    public function test_the_pass_says_the_box_is_on_hire(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        $this->get(route('yard.movements.gate-pass', $this->departure()))
            ->assertOk()
            ->assertSee('On hire from');
    }

    /** Every format the yard can print, not just the default. */
    public function test_every_format_names_the_renter(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        foreach (['full', 'half', 'half-custom'] as $format) {
            $this->get(route('yard.movements.gate-pass', [$this->departure(), 'format' => $format]))
                ->assertOk()
                ->assertSee('ABC Traders');
        }
    }

    // ── Nothing else moves ──────────────────────────────────────────────────

    /** No holder, so the pass reads exactly as it always did. */
    public function test_an_ordinary_release_names_the_visit_customer(): void
    {
        $this->release();

        $this->get(route('yard.movements.gate-pass', $this->departure()))
            ->assertOk()
            ->assertSee('Maersk Line')
            ->assertDontSee('On hire from');
    }

    // ── The return ──────────────────────────────────────────────────────────

    public function test_the_return_pass_names_the_renter_bringing_it_back(): void
    {
        $this->lease();
        $this->reLet();
        $this->release();

        Carbon::setTestNow('2026-03-25 10:00:00');
        $this->returnBox();

        $arrival = GateMovement::where('container_id', $this->container->id)
            ->where('movement_type', 'in')->latest('gate_in_time')->firstOrFail();

        $this->get(route('yard.movements.gate-pass', $arrival))
            ->assertOk()
            ->assertSee('ABC Traders');
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function lease(): LessorOnHire
    {
        return app(LessorOnHireService::class)->onHireInYard(
            $this->container->fresh(),
            ['lessor_id' => $this->line->id, 'on_hire_date' => '2026-03-10'],
            auth()->id(),
        );
    }

    private function reLet(): ContainerHire
    {
        return app(ContainerHireService::class)->onHire(
            $this->container->fresh(),
            ['on_hire_date' => '2026-03-12', 'hire_customer_id' => $this->renter->id],
            auth()->id(),
        );
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

    private function returnBox(): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('yard.gate.in'), [
            'job_type_id'       => YardJobType::where('job_type_code', 'EMPTY_RETURN')->value('id'),
            'return_reason'     => 'agent_return',
            'container_no'      => $this->container->container_no,
            'equipment_type_id' => $this->container->equipment_type_id,
            'customer_id'       => $this->line->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'WXY-1234',
        ]);
    }

    private function departure(): GateMovement
    {
        return GateMovement::where('container_id', $this->container->id)
            ->where('movement_type', 'out')->latest('gate_out_time')->firstOrFail();
    }

    private function arrive(): GateMovement
    {
        $eqt  = \App\Models\EquipmentType::firstOrFail();
        $type = YardJobType::where('job_type_code', 'LADEN_IN')->firstOrFail();

        $this->container->update([
            'equipment_type_id' => $eqt->id,
            'size'              => $eqt->size,
            'type_code'         => $eqt->type_code,
        ]);

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
            'size'            => $eqt->size,
            'container_type'  => $eqt->type_code,
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
