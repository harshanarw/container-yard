<?php

namespace Tests\Feature\Repair;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Damage;
use App\Models\Inquiry;
use App\Models\MrCode;
use Tests\Support\FeatureTestCase;

/**
 * Phase A — the expanded M&R master: "Corrosion" is now findable, the added
 * industry damage/repair types are seeded, and a new repair code maps to the
 * correct estimate repair_type on import.
 */
class MrCodeCatalogTest extends FeatureTestCase
{
    public function test_corrosion_is_findable_and_new_damage_types_are_seeded(): void
    {
        // Renamed from "Rust" so inspectors find it by "Corrosion".
        $rst = MrCode::where('type', 'damage')->where('code', 'RST')->first();
        $this->assertNotNull($rst);
        $this->assertStringContainsStringIgnoringCase('corrosion', $rst->name);

        foreach (['PIT', 'DIS', 'BLG', 'WRP', 'LSE', 'SCR', 'GOU', 'TRN', 'CHF', 'PEL',
                  'CON', 'ODR', 'WTR', 'ROT', 'LEK', 'INO'] as $code) {
            $this->assertDatabaseHas('mr_codes', ['type' => 'damage', 'code' => $code, 'is_active' => true]);
        }
    }

    public function test_new_repair_types_are_seeded(): void
    {
        foreach (['CRP', 'IST', 'RSL', 'RFT', 'TGT', 'RCD'] as $code) {
            $this->assertDatabaseHas('mr_codes', ['type' => 'repair', 'code' => $code, 'is_active' => true]);
        }
        // The Inspect code stays untouched (no clash with the new Insert code).
        $this->assertDatabaseHas('mr_codes', ['type' => 'repair', 'code' => 'INS', 'name' => 'Inspect Only']);
    }

    public function test_import_maps_a_new_repair_code_to_the_right_estimate_type(): void
    {
        $this->actingAsSystemAdmin();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create([
            'customer_id' => $customer->id, 'size' => '40', 'type_code' => 'HC',
        ]);
        $inquiry = Inquiry::create([
            'inquiry_no'   => 'INQ-MRC1',
            'container_id' => $container->id,
            'container_no' => $container->container_no,
            'size'         => $container->size,
            'type_code'    => $container->type_code,
            'customer_id'  => $customer->id,
            'inquiry_type' => 'damage_survey',
            'status'       => 'open',
            'priority'     => 'normal',
        ]);

        $pnl = MrCode::where('type', 'component')->where('code', 'PNL')->value('id');
        $gou = MrCode::where('type', 'damage')->where('code', 'GOU')->value('id');
        $crp = MrCode::where('type', 'repair')->where('code', 'CRP')->value('id');

        Damage::create([
            'inquiry_id'        => $inquiry->id,
            'component_code_id' => $pnl,
            'damage_code_id'    => $gou,
            'repair_code_id'    => $crp,
            'severity'          => 'moderate',
            'quantity'          => 1,
        ]);

        $res = $this->getJson(route('estimates.import-damages', $inquiry) . '?container_size=40&currency=USD&exchange_rate=1');
        $res->assertOk();

        $line = collect($res->json('lines'))->firstWhere('repair_code_id', $crp);
        $this->assertNotNull($line, 'Imported line for the Crop & Weld damage was not found.');
        $this->assertSame('weld', $line['repair_type'], 'Crop & Weld (CRP) must map to the weld repair_type.');
    }
}
