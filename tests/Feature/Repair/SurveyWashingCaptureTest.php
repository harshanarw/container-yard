<?php

namespace Tests\Feature\Repair;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Inquiry;
use Tests\Support\FeatureTestCase;

/**
 * Phase 1 — the survey captures washing intent (required + scope + type). A box
 * can be flagged washing-only (no damages). Later phases pull this into the
 * estimate as washing line(s).
 */
class SurveyWashingCaptureTest extends FeatureTestCase
{
    public function test_survey_stores_washing_only_box_with_no_damages(): void
    {
        $this->actingAsSystemAdmin();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create([
            'customer_id' => $customer->id, 'status' => 'in_yard', 'size' => '40', 'type_code' => 'HC',
        ]);

        $this->post(route('surveys.store'), [
            'container_id'       => $container->id,
            'customer_id'        => $customer->id,
            'inquiry_type'       => 'condition_survey',
            'priority'           => 'normal',
            'inspection_date'    => now()->toDateString(),
            'recommended_action' => 'no_action',
            // washing-only: no damages array at all
            'wash_required'      => 1,
            'wash_scope'         => 'both',
            'wash_type'          => 'chemical',
        ])->assertSessionHasNoErrors();

        $survey = Inquiry::latest('id')->firstOrFail();
        $this->assertTrue((bool) $survey->wash_required);
        $this->assertSame('both', $survey->wash_scope);
        $this->assertSame('chemical', $survey->wash_type);
        $this->assertSame(0, $survey->damages()->count(), 'Washing-only survey should have no damages.');
    }

    public function test_wash_fields_are_cleared_when_not_required(): void
    {
        $this->actingAsSystemAdmin();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create(['customer_id' => $customer->id, 'status' => 'in_yard']);

        // wash_scope/type sent but the flag is off → they must not persist.
        $this->post(route('surveys.store'), [
            'container_id'       => $container->id,
            'customer_id'        => $customer->id,
            'inquiry_type'       => 'condition_survey',
            'priority'           => 'normal',
            'inspection_date'    => now()->toDateString(),
            'recommended_action' => 'no_action',
            'wash_scope'         => 'internal',
            'wash_type'          => 'steam',
        ])->assertSessionHasNoErrors();

        $survey = Inquiry::latest('id')->firstOrFail();
        $this->assertFalse((bool) $survey->wash_required);
        $this->assertNull($survey->wash_scope);
        $this->assertNull($survey->wash_type);
    }

    public function test_survey_show_displays_the_washing_status(): void
    {
        $this->actingAsSystemAdmin();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create(['customer_id' => $customer->id]);
        $survey = Inquiry::create([
            'inquiry_no'    => 'INQ-WASH1',
            'container_id'  => $container->id,
            'container_no'  => $container->container_no,
            'size'          => $container->size,
            'type_code'     => $container->type_code,
            'customer_id'   => $customer->id,
            'inquiry_type'  => 'condition_survey',
            'status'        => 'open',
            'priority'      => 'normal',
            'wash_required' => true,
            'wash_scope'    => 'external',
            'wash_type'     => 'standard',
        ]);

        $this->get(route('surveys.show', $survey))
            ->assertOk()
            ->assertSee('External')
            ->assertSee('Standard');
    }
}
