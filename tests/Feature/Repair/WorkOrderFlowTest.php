<?php

namespace Tests\Feature\Repair;

use App\Models\Estimate;
use App\Models\RepairCategory;
use App\Models\WorkOrder;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for the front of the repair chain: an estimate is approved,
 * then a Work Order generated from it copies the estimate's line items (for the
 * chosen repair category) and moves the container into repair.
 */
class WorkOrderFlowTest extends FeatureTestCase
{
    public function test_estimate_approves(): void
    {
        $this->actingAsSystemAdmin();

        $estimate = Estimate::where('status', 'approved')->first();
        $this->assertNotNull($estimate, 'Expected a seeded estimate.');

        // Reset to draft so we exercise the real approve transition.
        $estimate->update(['status' => 'draft', 'approved_by' => null, 'approved_date' => null]);

        $this->patch(route('estimates.approve', $estimate))->assertSessionHasNoErrors();

        $estimate->refresh();
        $this->assertSame('approved', $estimate->status);
        $this->assertNotNull($estimate->approved_by);
    }

    public function test_work_order_from_approved_estimate_copies_lines_and_starts_repair(): void
    {
        $this->actingAsSystemAdmin();

        $estimate = Estimate::where('status', 'approved')->with('lineItems')->first();
        $this->assertNotNull($estimate, 'Expected a seeded approved estimate.');
        $this->assertTrue($estimate->lineItems->isNotEmpty(), 'Approved estimate has no line items.');

        // Categorise the estimate's lines — the seeder leaves repair_category_id
        // null, but in the app they carry a category (from the survey's MR repair
        // codes), and a Work Order is generated per category.
        $category = RepairCategory::query()->first();
        $this->assertNotNull($category, 'Expected a seeded repair category.');
        $estimate->lineItems()->update(['repair_category_id' => $category->id]);

        $expectedLines = $estimate->lineItems()->count();

        // Minimal payload: omit the nullable assigned_to / target_date /
        // instructions (a partial client would). This previously fataled on an
        // "Undefined array key" while building the WorkOrder row.
        $create = $this->post(route('work-orders.store'), [
            'estimate_id'        => $estimate->id,
            'repair_category_id' => $category->id,
            'priority'           => 'normal',
        ]);

        $create->assertSessionHasNoErrors();
        $create->assertRedirect();

        $wo = WorkOrder::where('estimate_id', $estimate->id)->latest('id')->first();
        $this->assertNotNull($wo, 'Work order was not created.');
        $this->assertSame('pending', $wo->status);
        $this->assertSame($category->id, $wo->repair_category_id);

        // All the category's estimate lines were copied onto the work order.
        $this->assertSame($expectedLines, $wo->lines()->count());

        // Repair has started → the container is now in repair.
        $this->assertDatabaseHas('containers', [
            'id'     => $estimate->container_id,
            'status' => 'in_repair',
        ]);
    }
}
