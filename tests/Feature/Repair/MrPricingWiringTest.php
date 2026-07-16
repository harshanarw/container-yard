<?php

namespace Tests\Feature\Repair;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Damage;
use App\Models\Inquiry;
use App\Models\MrCode;
use App\Services\RepairCategoryResolver;
use Tests\Support\FeatureTestCase;

/**
 * Phase B — the new M&R codes are fully wired: a survey using them imports
 * priced (tariff), charge-coded (charge mapping) and category-resolvable, and a
 * combo with no specific tariff still prices via the repair-only fallback.
 */
class MrPricingWiringTest extends FeatureTestCase
{
    private function surveyWithDamage(string $comp, string $damage, string $repair): Inquiry
    {
        $customer  = Customer::factory()->create();
        $container = Container::factory()->create([
            'customer_id' => $customer->id, 'size' => '40', 'type_code' => 'HC',
        ]);
        $inquiry = Inquiry::create([
            'inquiry_no'   => 'INQ-' . substr($comp . $repair, 0, 6),
            'container_id' => $container->id, 'container_no' => $container->container_no,
            'size'         => '40', 'type_code' => 'HC', 'customer_id' => $customer->id,
            'inquiry_type' => 'damage_survey', 'status' => 'open', 'priority' => 'normal',
        ]);
        Damage::create([
            'inquiry_id'        => $inquiry->id,
            'component_code_id' => MrCode::where('type', 'component')->where('code', $comp)->value('id'),
            'damage_code_id'    => MrCode::where('type', 'damage')->where('code', $damage)->value('id'),
            'repair_code_id'    => MrCode::where('type', 'repair')->where('code', $repair)->value('id'),
            'severity'          => 'moderate', 'quantity' => 1,
        ]);

        return $inquiry;
    }

    private function importLine(Inquiry $inquiry): ?array
    {
        $res = $this->getJson(route('estimates.import-damages', $inquiry) . '?container_size=40&currency=USD&exchange_rate=1');
        $res->assertOk();
        return collect($res->json('lines'))->first();
    }

    public function test_new_combo_imports_priced_and_charge_coded(): void
    {
        $this->actingAsSystemAdmin();

        // Panel × Gouge × Crop & Weld — a Phase-2 combo with a specific tariff rule.
        $line = $this->importLine($this->surveyWithDamage('PNL', 'GOU', 'CRP'));

        $this->assertNotNull($line);
        $this->assertGreaterThan(0, (float) $line['unit_price'], 'Crop & weld must be priced from the tariff.');
        $this->assertNotNull($line['charge_code_id'], 'The line must carry a charge code.');
        $this->assertTrue($line['_tariff_matched']);
    }

    public function test_repair_only_fallback_prices_an_uncovered_combo(): void
    {
        $this->actingAsSystemAdmin();

        // Sill × Loose × Tighten — no component-specific rule; the repair-only
        // fallback (component = null, repair = TGT) must still price it.
        $line = $this->importLine($this->surveyWithDamage('SIL', 'LSE', 'TGT'));

        $this->assertNotNull($line);
        $this->assertGreaterThan(0, (float) $line['unit_price'], 'Fallback tariff must price an uncovered combo.');
    }

    public function test_new_repair_resolves_to_a_category(): void
    {
        $pnl = MrCode::where('type', 'component')->where('code', 'PNL')->value('id');

        // Crop & Weld maps to the 'weld' estimate type; Panel resolves via the
        // component rule to a repair category.
        $category = app(RepairCategoryResolver::class)->resolve($pnl, 'weld');
        $this->assertNotNull($category, 'A panel weld line should resolve to a repair category.');
    }
}
