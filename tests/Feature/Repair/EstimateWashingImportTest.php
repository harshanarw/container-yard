<?php

namespace Tests\Feature\Repair;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Inquiry;
use Tests\Support\FeatureTestCase;

/**
 * Phase 2 — a survey's washing intent is pulled into the estimate as washing
 * line(s), like repair categories. Works for a washing-only survey (no damages):
 * both → internal + external, single scope → one, no tariff → 0-priced line.
 */
class EstimateWashingImportTest extends FeatureTestCase
{
    private function washingSurvey(string $scope, string $type = 'standard'): Inquiry
    {
        $customer  = Customer::factory()->create();
        $container = Container::factory()->create([
            'customer_id' => $customer->id, 'size' => '40', 'type_code' => 'HC',
        ]);

        return Inquiry::create([
            'inquiry_no'    => 'INQ-' . strtoupper($scope) . '-' . uniqid(),
            'container_id'  => $container->id,
            'container_no'  => $container->container_no,
            'size'          => $container->size,
            'type_code'     => $container->type_code,
            'customer_id'   => $customer->id,
            'inquiry_type'  => 'condition_survey',
            'status'        => 'open',
            'priority'      => 'normal',
            'wash_required' => true,
            'wash_scope'    => $scope,
            'wash_type'     => $type,
        ]);
    }

    private function import(Inquiry $survey)
    {
        return $this->getJson(
            route('estimates.import-damages', $survey) . '?container_size=40&currency=USD&exchange_rate=1'
        );
    }

    public function test_both_scope_washing_only_survey_yields_two_priced_lines(): void
    {
        $this->actingAsSystemAdmin();

        $res = $this->import($this->washingSurvey('both'));
        $res->assertOk();

        $this->assertSame(0, $res->json('damage_count'));
        $this->assertSame(2, $res->json('washing_count'));

        $wash = collect($res->json('lines'))->where('_washing', true);
        $this->assertCount(2, $wash);
        $this->assertEqualsCanonicalizing(['internal', 'external'], $wash->pluck('wash_scope')->all());
        // Seeded standard rates → priced, tariff matched, marked as washing repair type.
        $this->assertTrue($wash->every(fn ($l) => (float) $l['unit_price'] > 0));
        $this->assertTrue($wash->every(fn ($l) => $l['_tariff_matched'] === true));
        $this->assertTrue($wash->every(fn ($l) => $l['repair_type'] === 'clean_and_treat'));
    }

    public function test_single_scope_yields_one_line(): void
    {
        $this->actingAsSystemAdmin();

        $res = $this->import($this->washingSurvey('internal'));
        $res->assertOk();

        $this->assertSame(1, $res->json('washing_count'));
        $wash = collect($res->json('lines'))->where('_washing', true);
        $this->assertCount(1, $wash);
        $this->assertSame('internal', $wash->first()['wash_scope']);
    }

    public function test_unpriced_wash_type_still_creates_a_zero_line(): void
    {
        $this->actingAsSystemAdmin();

        // 'degas' has no seeded tariff → line is still created, at price 0.
        $res = $this->import($this->washingSurvey('external', 'degas'));
        $res->assertOk();

        $this->assertSame(1, $res->json('washing_count'));
        $line = collect($res->json('lines'))->firstWhere('_washing', true);
        $this->assertNotNull($line);
        $this->assertSame(0.0, (float) $line['unit_price']);
        $this->assertFalse($line['_tariff_matched']);
        $this->assertNull($line['washing_tariff_id']);
    }
}
