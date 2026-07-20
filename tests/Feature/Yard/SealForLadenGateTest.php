<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\YardJobType;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * Seal control for laden containers. When require_seal_for_laden is on, a laden
 * gate-in or gate-out must carry a seal number, or a documented no-seal reason.
 * Empty moves are never affected, and with the setting off nothing changes.
 */
class SealForLadenGateTest extends FeatureTestCase
{
    private function enableSealPolicy(): void
    {
        // Direct DB update + cache flush — a cached CompanySetting leaks across tests.
        DB::table('company_settings')->update(['require_seal_for_laden' => true]);
        CompanySetting::flushCache();
    }

    /** A dry (non-reefer) equipment type, so a laden gate-in doesn't also demand a reefer service type. */
    private function dryEquipment(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();
    }

    private function gateInPayload(array $overrides = []): array
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

        return array_merge([
            'job_type_id'       => $jobType->id,
            'container_no'      => 'SEAL1234567',
            'equipment_type_id' => $this->dryEquipment()->id,
            'customer_id'       => Customer::factory()->create()->id,
            'condition'         => 'sound',
            'cargo_status'      => 'laden',
        ], $overrides);
    }

    // ─── Gate-in ──────────────────────────────────────────────────────────────

    public function test_laden_gate_in_without_seal_is_blocked_when_policy_on(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => '']))
            ->assertSessionHasErrors('seal_no');

        $this->assertDatabaseMissing('containers', ['container_no' => 'SEAL1234567', 'status' => 'in_yard']);
    }

    public function test_laden_gate_in_with_seal_passes(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => 'SL-88231']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_no'  => 'SEAL1234567',
            'movement_type' => 'in',
            'seal_no'       => 'SL-88231',
        ]);
    }

    public function test_laden_gate_in_without_seal_passes_with_a_reason(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => '', 'no_seal_reason' => 'customs_exam']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_no'   => 'SEAL1234567',
            'movement_type'  => 'in',
            'no_seal_reason' => 'customs_exam',
        ]);
    }

    public function test_empty_gate_in_without_seal_is_unaffected_by_the_policy(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['cargo_status' => 'empty', 'seal_no' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('containers', ['container_no' => 'SEAL1234567', 'status' => 'in_yard']);
    }

    public function test_laden_gate_in_without_seal_passes_when_policy_off(): void
    {
        $this->actingAsSystemAdmin();
        // policy left OFF (default)

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('containers', ['container_no' => 'SEAL1234567', 'status' => 'in_yard']);
    }

    // ─── Gate-out ─────────────────────────────────────────────────────────────

    /** A releasable in-yard container with the given cargo status and a real equipment type. */
    private function containerInYard(string $cargoStatus): Container
    {
        return Container::factory()->create([
            'cargo_status'      => $cargoStatus,
            'equipment_type_id' => $this->dryEquipment()->id,
        ]);
    }

    private function gateOutPayload(Container $c, array $overrides = []): array
    {
        return array_merge([
            'container_no'  => $c->container_no,
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => 'RO-TEST-1',
        ], $overrides);
    }

    public function test_laden_gate_out_without_seal_is_blocked_when_policy_on(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();
        $container = $this->containerInYard('laden');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => '']))
            ->assertSessionHasErrors('seal_no');

        $this->assertDatabaseMissing('gate_movements', [
            'container_id'  => $container->id,
            'movement_type' => 'out',
        ]);
    }

    public function test_laden_gate_out_without_seal_passes_with_a_reason(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();
        $container = $this->containerInYard('laden');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => '', 'no_seal_reason' => 'broken_missing']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_id'   => $container->id,
            'movement_type'  => 'out',
            'no_seal_reason' => 'broken_missing',
        ]);
    }

    public function test_empty_gate_out_without_seal_is_unaffected_by_the_policy(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();
        $container = $this->containerInYard('empty');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_id'  => $container->id,
            'movement_type' => 'out',
        ]);
    }

    // ─── End-to-end: enable via the real settings form, then gate-in ───────────
    // Mirrors the actual operator flow (toggle the setting in Company Settings,
    // which flushes the settings cache, then record a laden gate-in). If this
    // passes but the live app still lets a laden box through, the cause is
    // environmental (stale code cache / wrong deploy), not the rule itself.

    public function test_enabling_via_settings_form_then_blocks_a_laden_gate_in_with_no_seal(): void
    {
        $this->actingAsSystemAdmin();

        // Turn the policy ON the way an admin does — through the settings form.
        $this->from(route('settings.company.index'))
            ->post(route('settings.company.update'), [
                'company_name'          => 'Test Yard',
                'require_seal_for_laden' => '1',
            ])->assertSessionHasNoErrors();

        $this->assertTrue(
            (bool) CompanySetting::current()->require_seal_for_laden,
            'require_seal_for_laden should read ON after saving the settings form.'
        );

        // Laden gate-in, seal and reason both blank (as the browser posts them:
        // empty strings, which the framework converts to null) → must be blocked.
        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => '', 'no_seal_reason' => '']))
            ->assertSessionHasErrors('seal_no');

        $this->assertDatabaseMissing('containers', ['container_no' => 'SEAL1234567', 'status' => 'in_yard']);
    }

    public function test_enabling_via_settings_form_then_allows_a_laden_gate_in_with_a_reason(): void
    {
        $this->actingAsSystemAdmin();

        $this->from(route('settings.company.index'))
            ->post(route('settings.company.update'), [
                'company_name'          => 'Test Yard',
                'require_seal_for_laden' => '1',
            ])->assertSessionHasNoErrors();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => '', 'no_seal_reason' => 'customs_exam']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_no'   => 'SEAL1234567',
            'movement_type'  => 'in',
            'no_seal_reason' => 'customs_exam',
        ]);
    }

    // ─── Rendered-form (markup) coverage ──────────────────────────────────────
    // These assert the gate page HTML actually contains the No-seal reason field
    // (hidden by default; the browser reveals it for laden). They catch the
    // "field disappeared from the page" class of regression. They do NOT execute
    // JavaScript — the show/hide-on-laden behaviour itself needs a browser test.

    public function test_gate_form_renders_the_no_seal_reason_field_when_policy_on(): void
    {
        $this->actingAsSystemAdmin();
        DB::table('company_settings')->update(['require_seal_for_laden' => true]);
        CompanySetting::flushCache();

        $res = $this->get(route('yard.gate'))->assertOk();

        // Both gate-in and gate-out reason pickers render, hidden by default.
        $res->assertSee('class="mt-2 d-none" id="noSealWrapIn"', false);
        $res->assertSee('class="mt-2 d-none" id="noSealWrapOut"', false);
        $res->assertSee('name="no_seal_reason"', false);
        // The documented reasons are offered.
        $res->assertSee('LCL / groupage');
        $res->assertSee('Customs examination');
        $res->assertSee('Seal broken / missing');
        // The cargo selector the reveal watches, and the laden badge.
        $res->assertSee('id="cargoStatusIn"', false);
        $res->assertSee('Required for laden');
    }

    public function test_gate_form_omits_the_no_seal_reason_field_when_policy_off(): void
    {
        $this->actingAsSystemAdmin();
        // policy left OFF (default) — the whole block should not render.

        $this->get(route('yard.gate'))
            ->assertOk()
            ->assertDontSee('name="no_seal_reason"', false)
            ->assertDontSee('id="noSealWrapIn"', false);
    }

    // ─── AJAX submit path ─────────────────────────────────────────────────────
    // The gate form posts via fetch (photo uploader) with the XHR header, and its
    // handler shows validation errors only from a 422 JSON. A plain redirect-back
    // was silently followed, so the operator saw no error while the movement was
    // (correctly) not saved. These assert the seal block returns a 422 for AJAX.

    public function test_ajax_laden_gate_in_without_seal_returns_422_with_seal_error(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $this->postJson(route('yard.gate.in'), $this->gateInPayload(['seal_no' => '', 'no_seal_reason' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('seal_no');

        $this->assertDatabaseMissing('containers', ['container_no' => 'SEAL1234567', 'status' => 'in_yard']);
    }

    public function test_ajax_laden_gate_out_without_seal_returns_422_with_seal_error(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();
        $container = $this->containerInYard('laden');

        $this->postJson(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => '', 'no_seal_reason' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('seal_no');

        $this->assertDatabaseMissing('gate_movements', [
            'container_id'  => $container->id,
            'movement_type' => 'out',
        ]);
    }
}
