<?php

namespace Tests\Feature\Repair;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\Estimate;
use App\Models\Inquiry;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for the front of the repair chain: recording a survey creates
 * an inquiry, and an estimate generated from that inquiry carries its line items
 * and links back to the survey. (Approval → Work Order is covered separately in
 * WorkOrderFlowTest.)
 */
class SurveyToEstimateFlowTest extends FeatureTestCase
{
    public function test_survey_creates_inquiry_then_estimate_is_generated_from_it(): void
    {
        $this->actingAsSystemAdmin();

        $customer  = Customer::factory()->create();
        $equipment = EquipmentType::query()->first();
        $container = Container::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'in_yard',
        ]);

        $this->assertNotNull($equipment, 'Expected a seeded equipment type.');

        // ── Record a survey → creates the inquiry ──
        $this->from(route('surveys.create'))->post(route('surveys.store'), [
            'container_id' => $container->id,
            'customer_id'  => $customer->id,
            'inquiry_type' => 'damage_survey',
            'priority'     => 'normal',
        ])->assertSessionHasNoErrors();

        $inquiry = Inquiry::where('container_id', $container->id)->latest('id')->first();
        $this->assertNotNull($inquiry, 'Survey (inquiry) was not created.');

        // ── Generate an estimate from the survey ──
        $create = $this->from(route('estimates.create', ['inquiry_id' => $inquiry->id]))
            ->post(route('estimates.store'), [
                'inquiry_id'        => $inquiry->id,
                'container_id'      => $container->id,
                'equipment_type_id' => $equipment->id,
                'customer_id'       => $customer->id,
                'estimate_date'     => now()->toDateString(),
                'valid_until'       => now()->addDays(30)->toDateString(),
                'currency'          => 'LKR',
                'exchange_rate'     => 1,
                'tax_applicable'    => 0,
                'priority'          => 'normal',
                'attach_pdf'        => 0,
                'attach_photos'     => 0,
                'line_items'        => [[
                    'component'   => 'Door panel',
                    'repair_type' => 'repair',
                    'qty'         => 1,
                    'unit_price'  => 500,
                ]],
            ]);

        $create->assertSessionHasNoErrors();
        $create->assertRedirect();

        $estimate = Estimate::where('inquiry_id', $inquiry->id)->latest('id')->first();
        $this->assertNotNull($estimate, 'Estimate was not created from the survey.');
        $this->assertSame('draft', $estimate->status);
        $this->assertSame($container->id, $estimate->container_id);
        $this->assertSame(1, $estimate->lineItems()->count());
        $this->assertGreaterThan(0, (float) $estimate->grand_total);
    }
}
