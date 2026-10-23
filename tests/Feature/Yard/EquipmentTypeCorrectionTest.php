<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\ReeferPlugSession;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageHandlingInvoiceLine;
use App\Models\YardJobType;
use Tests\Support\FeatureTestCase;

/**
 * Correcting an equipment type picked wrongly at the gate.
 *
 * The size and type a container arrives with reach three places — the container
 * master, both ends of the visit, and the tariff rows that price the stay — so
 * changing one afterwards is a correction with consequences rather than an edit.
 *
 * The container master is the one that matters for money, and that is not
 * obvious: `yard_storage` carries no equipment columns at all, and storage
 * billing resolves the type from the master at preview time. Correct only the
 * movement and the stay goes on pricing at the wrong rate with nothing to show
 * for it.
 *
 * Which is why this was built as its own guarded endpoint rather than a field on
 * the edit form. `updateMovement` *did* accept `equipment_type_id` — no form
 * posted it, nothing checked whether the visit had been billed, and it wrote
 * only the movement. One end of a record that has three.
 */
class EquipmentTypeCorrectionTest extends FeatureTestCase
{
    private Customer $line;
    private Container $container;
    private GateMovement $gateIn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsSystemAdmin();

        $this->line = Customer::factory()->create(['name' => 'Maersk Line']);
    }

    private function dry(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();
    }

    private function reefer(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => $e->isReefer()) ?? EquipmentType::query()->firstOrFail();
    }

    /** A different dry type from the one the box came in on, so a correction is a real change. */
    private function otherDry(EquipmentType $notThis): EquipmentType
    {
        return EquipmentType::all()->first(
            fn ($e) => ! $e->isReefer() && $e->id !== $notThis->id
        ) ?? $notThis;
    }

    private function gateIn(?EquipmentType $eqt = null): GateMovement
    {
        $eqt ??= $this->dry();

        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

        $this->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => 'EQTC1234567',
            'equipment_type_id' => $eqt->id,
            'customer_id'       => $this->line->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
        ])->assertSessionHasNoErrors();

        $this->container = Container::where('container_no', 'EQTC1234567')->firstOrFail();
        $this->gateIn    = GateMovement::where('container_id', $this->container->id)
            ->where('movement_type', 'in')->firstOrFail();

        return $this->gateIn;
    }

    private function check(EquipmentType $target): array
    {
        return $this->getJson(route('yard.movements.equipment-check', $this->gateIn)
            . '?equipment_type_id=' . $target->id)->json();
    }

    private function correct(EquipmentType $target, string $reason = 'Picked in error')
    {
        return $this->from(route('yard.movements.edit', $this->gateIn))
            ->patch(route('yard.movements.equipment-update', $this->gateIn), [
                'equipment_type_id' => $target->id,
                'reason'            => $reason,
            ]);
    }

    private function billTheVisit(string $status = 'issued'): void
    {
        $invoice = StorageHandlingInvoice::create([
            'invoice_no'          => 'SH-EQT-0001',
            'invoice_type'        => 'tax_invoice',
            'bill_type'           => StorageHandlingInvoice::BILL_STORAGE_HANDLING,
            'pricing_mode'        => StorageHandlingInvoice::PRICING_TARIFF,
            'manual_free_days'    => 0,
            'shipping_line_id'    => $this->line->id,
            'billing_party_id'    => $this->line->id,
            'invoice_date'        => now()->toDateString(),
            'due_date'            => now()->addMonth()->toDateString(),
            'invoice_currency'    => 'LKR',
            'exchange_rate'       => 1,
            'billing_period_from' => now()->startOfMonth()->toDateString(),
            'billing_period_to'   => now()->endOfMonth()->toDateString(),
            'storage_subtotal'    => 100, 'handling_subtotal' => 0, 'subtotal' => 100,
            'sscl_percentage'     => 0, 'sscl_amount' => 0,
            'vat_percentage'      => 0, 'vat_amount' => 0,
            'total_amount'        => 100, 'total_value' => 100,
            'status'              => $status,
        ]);

        StorageHandlingInvoiceLine::create([
            'invoice_id'              => $invoice->id,
            'container_id'            => $this->container->id,
            'container_no'            => $this->container->container_no,
            'container_size'          => $this->container->size,
            'equipment_type'          => $this->container->type_code,
            'cargo_status'            => 'empty',
            'gate_in_date'            => $this->gateIn->gate_in_time->toDateString(),
            'storage_from'            => $this->gateIn->gate_in_time->toDateString(),
            'storage_to'              => now()->toDateString(),
            'storage_total_days'      => 1,
            'storage_free_days'       => 0,
            'storage_chargeable_days' => 1,
            'storage_daily_rate'      => 100,
            'storage_currency'        => 'LKR',
            'storage_subtotal'        => 100,
            'has_lift_off'            => false, 'lift_off_rate' => 0,
            'has_lift_on'             => false, 'lift_on_rate'  => 0,
            'handling_currency'       => 'LKR', 'handling_subtotal' => 0,
            'line_total'              => 100, 'line_sscl' => 0, 'line_vat' => 0,
            'line_grand_total'        => 100,
        ]);
    }

    // ── A clean correction ───────────────────────────────────────────────────

    public function test_a_clean_correction_writes_the_master_and_the_movement(): void
    {
        $from = $this->dry();
        $this->gateIn($from);
        $to = $this->otherDry($from);

        $this->correct($to)->assertSessionHas('success');

        $container = $this->container->fresh();

        $this->assertSame($to->id, (int) $container->equipment_type_id);
        $this->assertSame($to->size, $container->size);
        $this->assertSame($to->type_code, $container->type_code);

        $movement = $this->gateIn->fresh();

        $this->assertSame($to->size, $movement->size);
        $this->assertSame($to->type_code, $movement->container_type);
    }

    /**
     * The master is the one that matters, and it is the one you would forget.
     * `yard_storage` holds no equipment columns; storage billing reads the
     * master at preview time, so a correction that stops at the movement leaves
     * the stay pricing at the old rate.
     */
    public function test_the_container_master_is_corrected_not_just_the_movement(): void
    {
        $from = $this->dry();
        $this->gateIn($from);
        $to = $this->otherDry($from);

        $this->correct($to);

        $this->assertSame(
            $to->id,
            (int) Container::whereKey($this->container->id)->value('equipment_type_id'),
        );
    }

    /** The departure belongs to the same box, and the handling bill reads it. */
    public function test_a_paired_gate_out_is_corrected_too(): void
    {
        $from = $this->dry();
        $this->gateIn($from);

        $this->post(route('yard.gate.out'), [
            'container_no'  => $this->container->container_no,
            'vehicle_plate' => 'TRUCK01',
            'driver_name'   => 'D Perera',
            'driver_ic'     => '901234567V',
            'release_order' => 'RO-EQT-1',
        ])->assertSessionHasNoErrors();

        $to = $this->otherDry($from);
        $this->correct($to)->assertSessionHas('success');

        $gateOut = GateMovement::where('container_id', $this->container->id)
            ->where('movement_type', 'out')->firstOrFail();

        $this->assertSame($to->size, $gateOut->size);
        $this->assertSame($to->type_code, $gateOut->container_type);
    }

    public function test_the_correction_is_recorded_on_the_audit_trail(): void
    {
        $from = $this->dry();
        $this->gateIn($from);
        $to = $this->otherDry($from);

        $this->correct($to, 'EIR and photos show the larger box');

        $this->assertDatabaseHas('audit_logs', [
            'event'      => 'equipment_type_corrected',
            'reference'  => 'EQTC1234567',
        ]);
    }

    // ── Refusals ─────────────────────────────────────────────────────────────

    public function test_a_billed_visit_cannot_be_corrected(): void
    {
        $from = $this->dry();
        $this->gateIn($from);
        $this->billTheVisit();

        $to = $this->otherDry($from);

        $this->assertFalse($this->check($to)['safe']);

        $this->correct($to)->assertSessionHas('error');

        $this->assertSame($from->id, (int) $this->container->fresh()->equipment_type_id);
    }

    /** A cancelled invoice reserves nothing — that is what cancel-and-re-raise is for. */
    public function test_a_cancelled_invoice_does_not_block(): void
    {
        $from = $this->dry();
        $this->gateIn($from);
        $this->billTheVisit('cancelled');

        $to = $this->otherDry($from);

        $this->assertTrue($this->check($to)['safe']);
    }

    /** Correcting to the type already recorded is not a correction. */
    public function test_the_same_type_is_refused(): void
    {
        $from = $this->dry();
        $this->gateIn($from);

        $this->assertFalse($this->check($from)['safe']);
    }

    // ── The reefer boundary ──────────────────────────────────────────────────

    /**
     * `GP → RF` is not a price change. `isReefer()` decides the PTI
     * requirement, whether a plug session opens, the mode asked for at the
     * gate and several M&R rungs — so a plug session recorded against this
     * visit would end up attached to a box that is no longer a reefer.
     */
    public function test_crossing_the_reefer_boundary_with_plug_sessions_is_refused(): void
    {
        $reefer = $this->reefer();
        $this->gateIn($reefer);

        ReeferPlugSession::create([
            'container_id'     => $this->container->id,
            'gate_movement_id' => $this->gateIn->id,
            'customer_id'      => $this->line->id,
            'status'           => 'pending',
        ]);

        $result = $this->check($this->dry());

        $this->assertFalse($result['safe']);
        $this->assertStringContainsString('reefer', strtolower(collect($result['blocks'])->pluck('message')->implode(' ')));
    }

    /** With no reefer work recorded it is allowed — and said out loud. */
    public function test_crossing_the_reefer_boundary_with_no_sessions_warns_but_allows(): void
    {
        $from = $this->dry();
        $this->gateIn($from);

        $result = $this->check($this->reefer());

        $this->assertTrue($result['safe']);
        $this->assertNotEmpty($result['warnings']);
    }

    // ── The doors that were standing open ────────────────────────────────────

    /**
     * `updateMovement` accepted this field, rewrote the movement's size from it,
     * checked nothing and left the master behind. No form posted it, so it was
     * reachable only by constructing the request — which is exactly what this
     * does.
     */
    public function test_the_movement_edit_no_longer_accepts_an_equipment_type(): void
    {
        $from = $this->dry();
        $this->gateIn($from);
        $to = $this->otherDry($from);

        $this->patch(route('yard.movements.update', $this->gateIn), [
            'equipment_type_id' => $to->id,
            'vehicle_plate'     => 'TRUCK02',
        ])->assertSessionHasNoErrors();

        $movement = $this->gateIn->fresh();

        $this->assertSame('TRUCK02', $movement->vehicle_plate, 'The rest of the edit still saves.');
        $this->assertSame($from->size, $movement->size, 'The size is not the edit screen\'s to change.');
        $this->assertSame($from->type_code, $movement->container_type);
    }

    /**
     * And the container master screen, which changed the same field freely for
     * a long time with no billing check and no movement sync — so a master and
     * its own movements could silently disagree.
     */
    public function test_the_container_master_screen_refuses_the_change_once_gated_in(): void
    {
        $from = $this->dry();
        $this->gateIn($from);
        $to = $this->otherDry($from);

        $this->from(route('containers.edit', $this->container))
            ->patch(route('containers.update', $this->container), [
                'container_no'      => $this->container->container_no,
                'category'          => $this->container->category ?? 'consignee',
                'equipment_type_id' => $to->id,
            ])
            ->assertSessionHasErrors('equipment_type_id');

        $this->assertSame($from->id, (int) $this->container->fresh()->equipment_type_id);
    }

    /** A container that has never been to the gate is still freely editable. */
    public function test_the_master_screen_still_allows_it_on_a_container_with_no_movements(): void
    {
        $from = $this->dry();
        $to   = $this->otherDry($from);

        $container = Container::factory()->create([
            'equipment_type_id' => $from->id,
            'size'              => $from->size,
            'type_code'         => $from->type_code,
            'customer_id'       => $this->line->id,
        ]);

        $this->from(route('containers.edit', $container))
            ->patch(route('containers.update', $container), [
                'container_no'      => $container->container_no,
                'category'          => $container->category ?? 'consignee',
                'equipment_type_id' => $to->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($to->id, (int) $container->fresh()->equipment_type_id);
    }
}
