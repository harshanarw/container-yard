<?php

namespace Tests\Feature\Yard;

use App\Models\CompanySetting;
use App\Models\EquipmentType;
use App\Models\GuardCapture;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * Phase A — the gate lookup surfaces the Guard Post status for a container, so
 * the officer can see clearance state and link a cleared capture inline.
 */
class GuardPostGateCheckTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsSystemAdmin();
    }

    private function enableGuardPost(): void
    {
        DB::table('company_settings')->update(['enable_guard_post' => true]);
        CompanySetting::flushCache();
    }

    private function capture(array $attrs): GuardCapture
    {
        return GuardCapture::create(array_merge([
            'reference_no' => 'GP-' . uniqid(),
            'captured_at'  => now(),
        ], $attrs));
    }

    public function test_feature_off_is_a_noop(): void
    {
        // enable_guard_post defaults to false → endpoint short-circuits.
        $this->getJson(route('yard.guard-post-check', ['container_no' => 'CSQU3054383', 'direction' => 'in']))
            ->assertOk()
            ->assertJson(['enabled' => false, 'match' => false]);
    }

    public function test_cleared_capture_is_actionable_with_prefill(): void
    {
        $this->enableGuardPost();
        $eqt = EquipmentType::where('iso_code', '22G1')->firstOrFail();

        $c = $this->capture([
            'direction'         => 'gate_in',
            'status'            => 'cleared',
            'cleared_at'        => now(),
            'container_number'  => 'CSQU3054383',
            'equipment_type_id' => $eqt->id,
            'vehicle_number'    => 'ABC-1234',
            'driver_name'       => 'Sunil',
        ]);

        $res = $this->getJson(route('yard.guard-post-check', ['container_no' => 'CSQU3054383', 'direction' => 'in']))
            ->assertOk()
            ->assertJson([
                'enabled'    => true,
                'match'      => true,
                'actionable' => true,
                'capture'    => ['status' => 'cleared', 'reference_no' => $c->reference_no],
            ]);

        $this->assertSame($c->id, $res->json('prefill.guard_capture_id'));
        $this->assertSame($eqt->id, $res->json('prefill.equipment_type_id'));
        $this->assertSame('ABC-1234', $res->json('prefill.vehicle_plate'));
    }

    public function test_pending_capture_is_shown_but_not_actionable(): void
    {
        $this->enableGuardPost();
        $this->capture([
            'direction'        => 'gate_in',
            'status'           => 'pending',
            'container_number' => 'CSQU3054383',
        ]);

        $this->getJson(route('yard.guard-post-check', ['container_no' => 'CSQU3054383', 'direction' => 'in']))
            ->assertOk()
            ->assertJson([
                'match'      => true,
                'actionable' => false,
                'capture'    => ['status' => 'pending'],
                'prefill'    => null,
            ]);
    }

    public function test_wrong_direction_does_not_match(): void
    {
        $this->enableGuardPost();
        $this->capture([
            'direction'        => 'gate_out',
            'status'           => 'cleared',
            'cleared_at'       => now(),
            'container_number' => 'CSQU3054383',
        ]);

        // Checking a gate-IN must not surface a gate-OUT capture.
        $this->getJson(route('yard.guard-post-check', ['container_no' => 'CSQU3054383', 'direction' => 'in']))
            ->assertOk()
            ->assertJson(['enabled' => true, 'match' => false]);
    }

    public function test_matched_capture_returns_a_rendered_detail_panel(): void
    {
        $this->enableGuardPost();
        $this->capture([
            'direction'            => 'gate_in',
            'status'               => 'cleared',
            'cleared_at'           => now(),
            'container_number'     => 'CSQU3054383',
            'driver_name'          => 'Sunil Perera',
            'vehicle_number'       => 'ABC-1234',
            'container_image_path' => 'guard/demo-container.jpg',
        ]);

        $res = $this->getJson(route('yard.guard-post-check', ['container_no' => 'CSQU3054383', 'direction' => 'in']))
            ->assertOk();

        $html = $res->json('panel_html');
        $this->assertIsString($html);
        $this->assertStringContainsString('Guard Post Verification', $html);
        $this->assertStringContainsString('Sunil Perera', $html);
        $this->assertStringContainsString('ABC-1234', $html);
        // Photo thumbnail carries the data attributes the data-driven lightbox reads.
        $this->assertStringContainsString('data-gp-url', $html);
    }

    public function test_pending_capture_still_returns_a_detail_panel(): void
    {
        // Non-cleared captures now surface the panel too (not just cleared ones),
        // so the officer can inspect the guard's photos before deciding.
        $this->enableGuardPost();
        $this->capture([
            'direction'        => 'gate_in',
            'status'           => 'pending',
            'container_number' => 'CSQU3054383',
            'driver_name'      => 'Nimal Silva',
        ]);

        $res = $this->getJson(route('yard.guard-post-check', ['container_no' => 'CSQU3054383', 'direction' => 'in']))
            ->assertOk()
            ->assertJson(['actionable' => false]);

        $this->assertStringContainsString('Nimal Silva', (string) $res->json('panel_html'));
    }
}
