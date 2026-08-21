<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJob;
use App\Models\YardJobType;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * containers:fix-gate-custody — repairing gate-out rows written before the
 * customer belonged to the visit.
 *
 * This command rewrites historical movement records, so the tests that matter
 * most are the ones proving it is conservative: it reports before it writes,
 * and it leaves alone anything it cannot pair to exactly one gate-in.
 */
class FixGateCustodyCommandTest extends FeatureTestCase
{
    private Customer $bringer;
    private Customer $other;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-01-15 12:00:00');
        $this->bringer = Customer::factory()->create(['name' => 'Bringer Lines']);
        $this->other   = Customer::factory()->create(['name' => 'Wrong Party Ltd']);
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function gateIn(string $no, string $at = '2027-01-10 08:00:00'): Container
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

        $equipment = EquipmentType::all()->first(fn ($e) => ! $e->isReefer())
            ?? EquipmentType::query()->firstOrFail();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => $no,
            'equipment_type_id' => $equipment->id,
            'customer_id'       => $this->bringer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => $at,
        ])->assertSessionHasNoErrors();

        return Container::where('container_no', $no)->firstOrFail();
    }

    private function gateOut(string $no, string $ro, string $at = '2027-01-12 09:00:00'): void
    {
        $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'  => $no,
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => $ro,
            'gate_out_time' => $at,
        ])->assertSessionHasNoErrors();
    }

    /** Put a gate-out back into the broken shape the old code produced. */
    private function breakGateOut(string $no): GateMovement
    {
        $out = GateMovement::where('container_no', $no)->where('movement_type', 'out')->latest('id')->firstOrFail();

        GateMovement::where('id', $out->id)->update([
            'customer_id' => $this->other->id,   // taken from the box, not the visit
            'yard_job_id' => null,               // orphaned from the visit
        ]);

        return $out->refresh();
    }

    private function out(string $no): GateMovement
    {
        return GateMovement::where('container_no', $no)->where('movement_type', 'out')->latest('id')->firstOrFail();
    }

    // ── Reporting before writing ─────────────────────────────────────────────

    public function test_a_dry_run_reports_without_changing_anything(): void
    {
        $this->gateIn('FIXC0000001');
        $this->gateOut('FIXC0000001', 'RO-FIX-1');
        $this->breakGateOut('FIXC0000001');

        $this->artisan('containers:fix-gate-custody', ['--container' => 'FIXC0000001'])
             ->assertSuccessful();

        $out = $this->out('FIXC0000001');

        $this->assertSame($this->other->id, (int) $out->customer_id, 'A dry run must not write.');
        $this->assertNull($out->yard_job_id);
    }

    // ── The repair ───────────────────────────────────────────────────────────

    public function test_fix_repoints_the_gate_out_to_its_visits_customer(): void
    {
        $this->gateIn('FIXC0000002');
        $this->gateOut('FIXC0000002', 'RO-FIX-2');
        $this->breakGateOut('FIXC0000002');

        $this->artisan('containers:fix-gate-custody', [
            '--container' => 'FIXC0000002',
            '--fix'       => true,
        ])->assertSuccessful();

        $out = $this->out('FIXC0000002');

        $this->assertSame($this->bringer->id, (int) $out->customer_id,
            'The release belongs to the party that brought the box in.');
        $this->assertNotNull($out->yard_job_id, 'And it is linked back to its visit.');
    }

    public function test_fix_repoints_the_containers_cached_customer_for_a_box_still_in_the_yard(): void
    {
        $container = $this->gateIn('FIXC0000003');

        // A master edit, as the old screen allowed.
        Container::where('id', $container->id)->update(['customer_id' => $this->other->id]);

        $this->artisan('containers:fix-gate-custody', [
            '--container' => 'FIXC0000003',
            '--fix'       => true,
        ])->assertSuccessful();

        $this->assertSame($this->bringer->id, (int) $container->refresh()->customer_id,
            'The container record caches the open visit; the visit is authoritative.');
    }

    public function test_a_correct_visit_is_left_alone(): void
    {
        $this->gateIn('FIXC0000004');
        $this->gateOut('FIXC0000004', 'RO-FIX-4');

        $before = $this->out('FIXC0000004');

        $this->artisan('containers:fix-gate-custody', [
            '--container' => 'FIXC0000004',
            '--fix'       => true,
        ])->assertSuccessful();

        $after = $this->out('FIXC0000004');

        $this->assertSame((int) $before->customer_id, (int) $after->customer_id);
        $this->assertEquals($before->updated_at, $after->updated_at, 'An untouched row stays untouched.');
    }

    /**
     * Two visits, each with its own party. The repair must not smear one
     * visit's customer across the other.
     */
    public function test_each_visit_is_repaired_against_its_own_gate_in(): void
    {
        $this->gateIn('FIXC0000005', '2027-01-02 08:00:00');
        $this->gateOut('FIXC0000005', 'RO-FIX-5A', '2027-01-03 09:00:00');

        $firstOut = $this->out('FIXC0000005');

        // Second visit, different party.
        $jobType = YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')->first();
        $equipment = EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => 'FIXC0000005',
            'equipment_type_id' => $equipment->id,
            'customer_id'       => $this->other->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => '2027-01-08 08:00:00',
        ])->assertSessionHasNoErrors();

        $this->gateOut('FIXC0000005', 'RO-FIX-5B', '2027-01-09 09:00:00');

        // Break both gate-outs.
        GateMovement::where('container_no', 'FIXC0000005')->where('movement_type', 'out')
            ->update(['customer_id' => $this->bringer->id, 'yard_job_id' => null]);

        $this->artisan('containers:fix-gate-custody', [
            '--container' => 'FIXC0000005',
            '--fix'       => true,
        ])->assertSuccessful();

        $outs = GateMovement::where('container_no', 'FIXC0000005')->where('movement_type', 'out')
            ->orderBy('gate_out_time')->get();

        $this->assertCount(2, $outs);
        $this->assertSame($this->bringer->id, (int) $outs[0]->customer_id, 'First visit keeps its own party.');
        $this->assertSame($this->other->id,   (int) $outs[1]->customer_id, 'Second visit keeps its own.');
    }

    // ── Conservatism ─────────────────────────────────────────────────────────

    /** A gate-out with no gate-in to pair to must be reported, not guessed at. */
    public function test_an_unpairable_gate_out_is_left_alone(): void
    {
        $container = $this->gateIn('FIXC0000006');
        $this->gateOut('FIXC0000006', 'RO-FIX-6');

        $out = $this->breakGateOut('FIXC0000006');

        // Remove the gate-in, leaving the release with no visit to belong to.
        GateMovement::where('container_no', 'FIXC0000006')->where('movement_type', 'in')->delete();

        $this->artisan('containers:fix-gate-custody', [
            '--container' => 'FIXC0000006',
            '--fix'       => true,
        ])->assertSuccessful();

        $this->assertSame($this->other->id, (int) $this->out('FIXC0000006')->customer_id,
            'With no unambiguous visit, a wrong guess is worse than an untouched row.');
    }

    public function test_an_unknown_container_number_fails_loudly(): void
    {
        $this->artisan('containers:fix-gate-custody', ['--container' => 'NOPE0000000'])
             ->assertFailed();
    }
}
