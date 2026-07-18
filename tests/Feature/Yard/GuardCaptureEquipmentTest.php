<?php

namespace Tests\Feature\Yard;

use App\Models\EquipmentType;
use App\Models\GuardCapture;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * Phase 2 — a guard capture resolves and stores a real equipment type from the
 * ISO size/type code (typed or OCR-read), so the gate-in hand-off is exact.
 * (The OCR extraction of the ISO code / weights needs Tesseract, so only the
 * deterministic resolution + persistence is asserted here.)
 */
class GuardCaptureEquipmentTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsSystemAdmin();
        DB::table('company_settings')->update(['enable_guard_post' => true]);
        \App\Models\CompanySetting::flushCache();
    }

    public function test_capture_resolves_equipment_type_from_the_iso_code(): void
    {
        $eqt = EquipmentType::where('iso_code', '22G1')->firstOrFail(); // seeded

        $this->post(route('guard-post.store'), [
            'direction'        => 'gate_in',
            'container_number' => 'CSQU3054383',
            'iso_code'         => '22G1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('guard_captures', [
            'iso_code'          => '22G1',
            'equipment_type_id' => $eqt->id,
        ]);

        $capture = GuardCapture::latest('id')->first();
        $this->assertSame($eqt->id, $capture->equipmentType->id);
    }

    public function test_unknown_iso_code_leaves_equipment_type_null(): void
    {
        // Valid shape, but no EquipmentType has this ISO code.
        $this->post(route('guard-post.store'), [
            'direction'        => 'gate_in',
            'container_number' => 'CSQU3054383',
            'iso_code'         => '99Z9',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('guard_captures', [
            'iso_code'          => '99Z9',
            'equipment_type_id' => null,
        ]);
    }
}
