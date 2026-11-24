<?php

namespace Tests\Feature\Yard;

use App\Models\AuditLog;
use App\Models\CargoTransfer;
use App\Models\CompanySetting;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJobType;
use App\Support\ContainerVisitState;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * Gate-out reads cargo status and seal from the visit, not the container
 * master (Rule 4; Phase 1 of docs/architecture-gap-plan.md).
 *
 * The complaint this pins: a laden box gated in without a seal, given its seal
 * on the movement edit screen, was refused at gate-out with the seal field
 * empty, because gate-out read the master and the edit never reaches it.
 *
 * Each test asserts the distinguishing precondition first, that the master
 * disagrees with the visit, so it cannot pass by reading the master.
 */
class GateOutFromVisitTest extends FeatureTestCase
{
    private const BOX = 'VSTU1234565';

    private function enableSealPolicy(): void
    {
        DB::table('company_settings')->update(['require_seal_for_laden' => true]);
        CompanySetting::flushCache();
    }

    private function dryEquipment(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();
    }

    /** Gate a box in through the real route and return its arrival movement. */
    private function gateInBox(array $overrides = []): GateMovement
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', 'LADEN_IN')
            ->first()
            ?? YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
                ->where('job_type_code', '!=', 'EMPTY_RETURN')->firstOrFail();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), array_merge([
            'job_type_id'       => $jobType->id,
            'container_no'      => self::BOX,
            'equipment_type_id' => $this->dryEquipment()->id,
            'customer_id'       => Customer::factory()->create()->id,
            'condition'         => 'sound',
            'cargo_status'      => 'laden',
            'vehicle_plate'     => 'TRUCK01',
        ], $overrides))->assertSessionHasNoErrors();

        return GateMovement::where('container_no', self::BOX)->where('movement_type', 'in')->latest('id')->firstOrFail();
    }

    private function gateOutFields(array $overrides = []): array
    {
        return array_merge([
            'container_no'  => self::BOX,
            'vehicle_plate' => 'OUT1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => 'RO-1',
        ], $overrides);
    }

    private function departure(): ?GateMovement
    {
        return GateMovement::where('container_no', self::BOX)->where('movement_type', 'out')->latest('id')->first();
    }

    // ── The complaint ─────────────────────────────────────────────────────────

    public function test_seal_added_on_the_edit_screen_is_offered_and_accepted_at_gate_out(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $arrival = $this->gateInBox(['seal_no' => '', 'no_seal_reason' => 'broken_missing']);

        $this->from(route('yard.movements.edit', $arrival))
            ->patch(route('yard.movements.update', $arrival), ['seal_no' => 'SL-55501', 'no_seal_reason' => ''])
            ->assertSessionHasNoErrors();

        // Precondition: the edit reached the movement and not the master.
        $this->assertSame('SL-55501', $arrival->fresh()->seal_no);
        $this->assertNotSame('SL-55501', Container::where('container_no', self::BOX)->value('seal_no'));

        $this->getJson(route('yard.container-lookup', ['container_no' => self::BOX]))
            ->assertOk()
            ->assertJson(['cargo_status' => 'laden', 'seal_no' => 'SL-55501']);

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutFields(['seal_no' => 'SL-55501']))
            ->assertSessionHasNoErrors();

        $out = $this->departure();
        $this->assertNotNull($out);
        $this->assertSame('laden', $out->cargo_status);
        $this->assertSame('SL-55501', $out->seal_no);
        $this->assertNull($out->seal_change_reason);
    }

    public function test_cargo_status_corrected_on_the_gate_in_decides_the_gate_out(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $arrival = $this->gateInBox(['cargo_status' => 'empty']);

        $this->from(route('yard.movements.edit', $arrival))
            ->patch(route('yard.movements.update', $arrival), ['cargo_status' => 'laden', 'seal_no' => 'SL-77002'])
            ->assertSessionHasNoErrors();

        // Precondition: the master still says Empty; only the visit says Laden.
        $this->assertSame('empty', Container::where('container_no', self::BOX)->value('cargo_status'));

        // Laden from the visit, so the seal rule applies...
        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutFields(['seal_no' => '']))
            ->assertSessionHasErrors('seal_no');
        $this->assertNull($this->departure());

        // ...and the departure records Laden, so the CODECO agrees with the arrival.
        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutFields(['seal_no' => 'SL-77002']))
            ->assertSessionHasNoErrors();
        $this->assertSame('laden', $this->departure()->cargo_status);
    }

    // ── Seal changed at gate-out (D9) ─────────────────────────────────────────

    public function test_a_different_seal_at_gate_out_needs_a_reason(): void
    {
        $this->actingAsSystemAdmin();
        $this->gateInBox(['seal_no' => 'SL-10001']);

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutFields(['seal_no' => 'SL-99999']))
            ->assertSessionHasErrors('seal_change_reason');
        $this->assertNull($this->departure());

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutFields([
                'seal_no'            => 'SL-99999',
                'seal_change_reason' => 'Customs examination, re-sealed',
            ]))
            ->assertSessionHasNoErrors();

        $out = $this->departure();
        $this->assertSame('SL-99999', $out->seal_no);
        $this->assertSame('Customs examination, re-sealed', $out->seal_change_reason);

        $this->assertTrue(
            AuditLog::where('subject_type', GateMovement::class)
                ->where('subject_id', $out->id)
                ->where('description', 'like', 'Seal changed at gate-out%')
                ->exists(),
            'A changed seal should leave its own audit line.'
        );
    }

    public function test_a_gate_out_that_sends_no_seal_field_carries_the_arrival_seal_over(): void
    {
        $this->actingAsSystemAdmin();
        $this->gateInBox(['seal_no' => 'SL-40004']);

        $fields = $this->gateOutFields();
        $this->assertArrayNotHasKey('seal_no', $fields); // precondition: the field is absent, not blank

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $fields)
            ->assertSessionHasNoErrors();

        $this->assertSame('SL-40004', $this->departure()->seal_no);
        $this->assertNull($this->departure()->seal_change_reason);
    }

    public function test_clearing_the_pre_filled_seal_is_a_change(): void
    {
        $this->actingAsSystemAdmin();
        $this->gateInBox(['seal_no' => 'SL-40004']);

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutFields(['seal_no' => '']))
            ->assertSessionHasErrors('seal_change_reason');
    }

    public function test_the_same_seal_retyped_differently_is_not_a_change(): void
    {
        $this->actingAsSystemAdmin();
        $this->gateInBox(['seal_no' => 'SL-10001']);

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutFields(['seal_no' => ' sl-10001 ']))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->departure()->seal_change_reason);
    }

    public function test_a_seal_given_to_a_box_that_arrived_without_one_needs_no_reason(): void
    {
        $this->actingAsSystemAdmin();
        $this->gateInBox(['seal_no' => '']);

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutFields(['seal_no' => 'SL-20002']))
            ->assertSessionHasNoErrors();

        $this->assertSame('SL-20002', $this->departure()->seal_no);
    }

    public function test_changing_the_departure_seal_on_the_edit_screen_needs_the_reason_too(): void
    {
        $this->actingAsSystemAdmin();
        $this->gateInBox(['seal_no' => 'SL-10001']);
        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutFields(['seal_no' => 'SL-10001']))
            ->assertSessionHasNoErrors();
        $out = $this->departure();

        $this->from(route('yard.movements.edit', $out))
            ->patch(route('yard.movements.update', $out), ['seal_no' => 'SL-30003', 'seal_change_reason' => ''])
            ->assertSessionHasErrors('seal_change_reason');
        $this->assertSame('SL-10001', $out->fresh()->seal_no);

        $this->from(route('yard.movements.edit', $out))
            ->patch(route('yard.movements.update', $out), ['seal_no' => 'SL-30003', 'seal_change_reason' => 'Seal broken at loading'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Seal broken at loading', $out->fresh()->seal_change_reason);
    }

    // ── Movement edit: a laden box with no seal is not a dead end ─────────────

    public function test_an_empty_gate_in_corrected_to_laden_can_record_a_no_seal_reason(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();
        $arrival = $this->gateInBox(['cargo_status' => 'empty']);

        $this->from(route('yard.movements.edit', $arrival))
            ->patch(route('yard.movements.update', $arrival), ['cargo_status' => 'laden', 'seal_no' => ''])
            ->assertSessionHasErrors('seal_no');

        $this->from(route('yard.movements.edit', $arrival))
            ->patch(route('yard.movements.update', $arrival), ['cargo_status' => 'laden', 'seal_no' => '', 'no_seal_reason' => 'lcl'])
            ->assertSessionHasNoErrors();

        $this->assertSame('laden', $arrival->fresh()->cargo_status);
        $this->assertSame('lcl', $arrival->fresh()->no_seal_reason);
    }

    // ── The resolver's other sources ──────────────────────────────────────────

    public function test_a_box_with_no_recorded_arrival_falls_back_to_the_master(): void
    {
        $this->actingAsSystemAdmin();
        $box = Container::factory()->create(['cargo_status' => 'laden', 'seal_no' => 'SL-M1']);

        $state = ContainerVisitState::for($box);

        $this->assertSame(ContainerVisitState::FROM_CONTAINER, $state->source);
        $this->assertSame('laden', $state->cargoStatus);
        $this->assertSame('SL-M1', $state->sealNo);
    }

    public function test_a_substitute_holding_transferred_cargo_reads_laden(): void
    {
        $this->actingAsSystemAdmin();
        $customer = Customer::factory()->create();

        $source = Container::factory()->create(['status' => 'in_yard', 'cargo_status' => 'laden']);
        $sourceIn = GateMovement::create([
            'container_id' => $source->id, 'container_no' => $source->container_no,
                'size' => $source->size, 'container_type' => $source->type_code,
            'customer_id' => $customer->id, 'movement_type' => 'in', 'cargo_status' => 'laden',
            'gate_in_time' => now()->subDays(2), 'created_by' => auth()->id(),
        ]);

        $substitute = Container::factory()->create(['status' => 'in_yard', 'cargo_status' => 'empty']);
        GateMovement::create([
            'container_id' => $substitute->id, 'container_no' => $substitute->container_no,
                'size' => $substitute->size, 'container_type' => $substitute->type_code,
            'customer_id' => $customer->id, 'movement_type' => 'in', 'cargo_status' => 'empty',
            'gate_in_time' => now()->subDays(5), 'created_by' => auth()->id(),
        ]);

        // Precondition: the substitute arrived empty.
        $this->assertSame('empty', ContainerVisitState::for($substitute)->cargoStatus);

        CargoTransfer::create([
            'customer_id' => $customer->id,
            'source_container_id' => $source->id, 'source_gate_movement_id' => $sourceIn->id,
            'substitute_container_id' => $substitute->id, 'substitute_source' => CargoTransfer::SOURCE_YARD_OWNED,
            'transfer_date' => now()->toDateString(), 'status' => 'active',
            'created_by' => auth()->id(), 'updated_by' => auth()->id(),
        ]);

        $state = ContainerVisitState::for($substitute);
        $this->assertSame(ContainerVisitState::FROM_CARGO_TRANSFER, $state->source);
        $this->assertSame('laden', $state->cargoStatus);
        $this->assertNull($state->sealNo);
    }

    // ── The report ────────────────────────────────────────────────────────────

    public function test_the_report_lists_a_departure_that_disagrees_with_its_arrival(): void
    {
        $this->actingAsSystemAdmin();
        $box = Container::factory()->create(['container_no' => 'MISU1234566']);

        foreach ([['in', 'laden', 'gate_in_time', 3], ['out', 'empty', 'gate_out_time', 1]] as [$type, $cargo, $timeCol, $daysAgo]) {
            GateMovement::create([
                'container_id' => $box->id, 'container_no' => $box->container_no,
                'size' => $box->size, 'container_type' => $box->type_code,
                'customer_id' => $box->customer_id, 'movement_type' => $type, 'cargo_status' => $cargo,
                $timeCol => now()->subDays($daysAgo), 'created_by' => auth()->id(),
            ]);
        }

        $this->artisan('movements:cargo-mismatch', ['--container' => 'MISU1234566'])
            ->expectsOutputToContain('MISU1234566')
            ->assertSuccessful();
    }

    public function test_the_report_leaves_out_a_box_emptied_by_a_cargo_transfer(): void
    {
        $this->actingAsSystemAdmin();
        $box = Container::factory()->create(['container_no' => 'TRFU1234560']);

        GateMovement::create([
            'container_id' => $box->id, 'container_no' => $box->container_no,
                'size' => $box->size, 'container_type' => $box->type_code,
            'customer_id' => $box->customer_id, 'movement_type' => 'in', 'cargo_status' => 'laden',
            'gate_in_time' => now()->subDays(3), 'created_by' => auth()->id(),
        ]);
        GateMovement::create([
            'container_id' => $box->id, 'container_no' => $box->container_no,
                'size' => $box->size, 'container_type' => $box->type_code,
            'customer_id' => $box->customer_id, 'movement_type' => 'out', 'cargo_status' => 'empty',
            'gate_out_purpose' => 'CARGO_RENTAL_OUT',
            'gate_out_time' => now()->subDay(), 'created_by' => auth()->id(),
        ]);

        $this->artisan('movements:cargo-mismatch', ['--container' => 'TRFU1234560'])
            ->expectsOutputToContain('No departure disagrees')
            ->assertSuccessful();
    }
}
