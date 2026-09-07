<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\ReeferPlugSession;
use App\Models\YardJobType;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Non-Operating Reefers at the gate (Phase 1).
 *
 * A reefer carrying dry cargo with the compressor off is a NOR — ordinary
 * practice, and until now unrecordable: the gate demanded a service type and
 * opened a plug session for any laden reefer.
 *
 * The half of this file that matters most is what does **not** change. An
 * operating reefer must behave exactly as it did, and `cargo_status` must keep
 * saying `laden` for a loaded NOR — that is what keeps handling tariffs, storage
 * invoice lines and the Weekly Performance grid correct, and it is the whole
 * reason this went on its own field instead of into `cargo_status`.
 */
class NonOperatingReeferTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-07 09:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── What must not change ────────────────────────────────────────────────

    /** An operating reefer behaves exactly as it did before NOR existed. */
    public function test_a_laden_operating_reefer_still_needs_a_service_type(): void
    {
        $this->gateIn('RFOP0000001', 'laden', reeferMode: 'operating', serviceType: null)
            ->assertSessionHasErrors('reefer_service_type');

        $this->assertSame(0, GateMovement::where('container_no', 'RFOP0000001')->count());
    }

    public function test_a_laden_operating_reefer_still_opens_a_plug_session(): void
    {
        $this->gateIn('RFOP0000002', 'laden', reeferMode: 'operating', serviceType: 'long_term')
            ->assertSessionHasNoErrors();

        $movement = GateMovement::where('container_no', 'RFOP0000002')->firstOrFail();

        $this->assertSame('operating', $movement->reefer_mode);
        $this->assertSame(1, ReeferPlugSession::where('gate_movement_id', $movement->id)->count());
    }

    /** A dry box never carries a mode at all — the question does not arise. */
    public function test_a_dry_container_stores_no_reefer_mode(): void
    {
        $this->gateInDry('DRYB0000001')->assertSessionHasNoErrors();

        $this->assertNull(GateMovement::where('container_no', 'DRYB0000001')->firstOrFail()->reefer_mode);
    }

    // ── NOR ─────────────────────────────────────────────────────────────────

    /** The case this was built for: dry cargo in a reefer box. */
    public function test_a_laden_nor_needs_no_service_type_and_opens_no_plug_session(): void
    {
        $this->gateIn('NORB0000001', 'laden', reeferMode: 'non_operating', serviceType: null)
            ->assertSessionHasNoErrors();

        $movement = GateMovement::where('container_no', 'NORB0000001')->firstOrFail();

        $this->assertSame('non_operating', $movement->reefer_mode);
        $this->assertSame(0, ReeferPlugSession::where('gate_movement_id', $movement->id)->count());
    }

    /**
     * The test that proves the two axes stayed apart.
     *
     * A loaded NOR is still `laden`. Handling tariffs, storage invoice lines and
     * the Weekly Performance grid all key on `cargo_status`, so anything that
     * quietly turned a NOR into an "empty" would reach billing.
     */
    public function test_a_laden_nor_is_still_recorded_as_laden(): void
    {
        $this->gateIn('NORB0000002', 'laden', reeferMode: 'non_operating', serviceType: null)
            ->assertSessionHasNoErrors();

        $this->assertSame('laden', GateMovement::where('container_no', 'NORB0000002')->firstOrFail()->cargo_status);
    }

    // ── Defaults ────────────────────────────────────────────────────────────

    /** Omitted on a laden reefer means operating — today's behaviour, unchanged. */
    public function test_a_laden_reefer_defaults_to_operating(): void
    {
        $this->gateIn('DFLT0000001', 'laden', reeferMode: null, serviceType: 'pti')
            ->assertSessionHasNoErrors();

        $this->assertSame('operating', GateMovement::where('container_no', 'DFLT0000001')->firstOrFail()->reefer_mode);
    }

    /** An empty reefer is not running unless somebody says so. */
    public function test_an_empty_reefer_defaults_to_nor(): void
    {
        $this->gateIn('DFLT0000002', 'empty', reeferMode: null, serviceType: null)
            ->assertSessionHasNoErrors();

        $this->assertSame('non_operating', GateMovement::where('container_no', 'DFLT0000002')->firstOrFail()->reefer_mode);
    }

    /** But it can be overridden — feeder movements run empty reefers. */
    public function test_an_empty_reefer_can_be_marked_operating(): void
    {
        $this->gateIn('DFLT0000003', 'empty', reeferMode: 'operating', serviceType: null)
            ->assertSessionHasNoErrors();

        $movement = GateMovement::where('container_no', 'DFLT0000003')->firstOrFail();

        $this->assertSame('operating', $movement->reefer_mode);
        // No plug session for an empty reefer even when operating: none is
        // created today, and widening that is a separate change.
        $this->assertSame(0, ReeferPlugSession::where('gate_movement_id', $movement->id)->count());
    }

    // ── The label ───────────────────────────────────────────────────────────

    /** Laden only. An empty NOR is the ordinary state of an empty reefer. */
    public function test_only_a_laden_nor_carries_the_label(): void
    {
        $laden = $this->movementFor('laden', 'non_operating');
        $empty = $this->movementFor('empty', 'non_operating');
        $oper  = $this->movementFor('laden', 'operating');

        $this->assertTrue($laden->isLadenNor());
        $this->assertFalse($empty->isLadenNor(), 'An empty NOR needs no flag.');
        $this->assertFalse($oper->isLadenNor());
    }

    /**
     * Historical movements predate the column. Null on a reefer reads as
     * operating, because that is how every one of them behaved when recorded —
     * treating them as NOR would rewrite history nobody entered.
     */
    public function test_a_movement_recorded_before_the_column_reads_as_operating(): void
    {
        $movement = $this->movementFor('laden', null);

        $this->assertTrue($movement->isOperatingReefer());
        $this->assertFalse($movement->isNonOperatingReefer());
        $this->assertFalse($movement->isLadenNor());
    }

    // ── Guards ──────────────────────────────────────────────────────────────

    public function test_an_unknown_reefer_mode_is_rejected(): void
    {
        $this->gateIn('BADM0000001', 'laden', reeferMode: 'sometimes', serviceType: 'pti')
            ->assertSessionHasErrors('reefer_mode');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function reeferEquipment(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => $e->isReefer())
            ?? $this->fail('No reefer equipment type is seeded.');
    }

    private function dryEquipment(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => ! $e->isReefer())
            ?? $this->fail('No dry equipment type is seeded.');
    }

    private function gateIn(string $containerNo, string $cargo, ?string $reeferMode, ?string $serviceType)
    {
        return $this->post(route('yard.gate.in'), array_filter([
            'job_type_id'         => $this->jobType()->id,
            'container_no'        => $containerNo,
            'equipment_type_id'   => $this->reeferEquipment()->id,
            'customer_id'         => $this->customer->id,
            'condition'           => 'sound',
            'cargo_status'        => $cargo,
            'vehicle_plate'       => 'TRUCK01',
            'reefer_mode'         => $reeferMode,
            'reefer_service_type' => $serviceType,
        ], fn ($v) => $v !== null));
    }

    private function gateInDry(string $containerNo)
    {
        return $this->post(route('yard.gate.in'), [
            'job_type_id'       => $this->jobType()->id,
            'container_no'      => $containerNo,
            'equipment_type_id' => $this->dryEquipment()->id,
            'customer_id'       => $this->customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
        ]);
    }

    private function jobType(): YardJobType
    {
        return YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')->firstOrFail();
    }

    /** A movement built directly, for the model-level rules. */
    private function movementFor(string $cargo, ?string $mode): GateMovement
    {
        $container = Container::factory()->create(['customer_id' => $this->customer->id]);

        return GateMovement::create([
            'container_id'   => $container->id,
            'container_no'   => $container->container_no,
            'customer_id'    => $this->customer->id,
            'movement_type'  => 'in',
            'size'           => '40',
            'container_type' => 'RF',
            'cargo_status'   => $cargo,
            'reefer_mode'    => $mode,
            'gate_in_time'   => now(),
            'created_by'     => auth()->id(),
        ]);
    }
}
