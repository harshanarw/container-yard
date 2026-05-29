<?php

namespace Database\Seeders;

use App\Models\Estimate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use Illuminate\Database\Seeder;

class WorkOrderSeeder extends Seeder
{
    /**
     * Seed sample Work Order records (demo/test data).
     *
     * Creates work orders from approved repair estimates.
     * This is optional test data — not run by default.
     *
     * Usage: php artisan db:seed --class=WorkOrderSeeder
     * Requires: EstimateSeeder (estimates must exist first)
     */
    public function run(): void
    {
        $approvedEstimates = Estimate::where('status', 'approved')
            ->with('lineItems', 'inquiry.container', 'createdBy')
            ->get();

        if ($approvedEstimates->isEmpty()) {
            $this->command->warn('No approved estimates found. Mark some estimates as "approved" first.');
            return;
        }

        $workshopSupervisor = User::where('role', 'yard_supervisor')->first();
        $workshopSupervisor = $workshopSupervisor ?: User::first();

        $woCounter = 1;

        foreach ($approvedEstimates as $estimate) {
            if (!$estimate->inquiry || !$estimate->inquiry->container) {
                continue;
            }

            $woNo = 'WO-' . str_pad($woCounter++, 6, '0', STR_PAD_LEFT);

            $workOrder = WorkOrder::create([
                'work_order_no'        => $woNo,
                'estimate_id'          => $estimate->id,
                'inquiry_id'           => $estimate->inquiry_id,
                'container_id'         => $estimate->inquiry->container_id,
                'customer_id'          => $estimate->customer_id,
                'equipment_type_id'    => $estimate->equipment_type_id,
                'work_type'            => $estimate->rh_type ?? 'standard_repair',
                'priority'             => 'normal',
                'scheduled_start_date' => $estimate->created_at->addDays(1)->toDateString(),
                'scheduled_end_date'   => $estimate->created_at->addDays(5)->toDateString(),
                'actual_start_date'    => null,
                'actual_end_date'      => null,
                'status'               => 'scheduled',
                'total_labor_hours'    => $estimate->lineItems->sum('std_labor_hours') ?? 0,
                'total_material_cost'  => $estimate->lineItems->sum(function ($line) {
                    return ($line->material_qty ?? 0) * ($line->material_rate ?? 0);
                }),
                'notes'                => "Repair work order for {$estimate->inquiry->container->container_no}",
                'assigned_to'          => $workshopSupervisor->id,
                'created_by'           => $estimate->created_by,
                'created_at'           => $estimate->created_at->addDays(1),
                'updated_at'           => $estimate->created_at->addDays(1),
            ]);

            // Create work order lines from estimate lines
            foreach ($estimate->lineItems as $estimateLine) {
                WorkOrderLine::create([
                    'work_order_id'          => $workOrder->id,
                    'estimate_line_item_id'  => $estimateLine->id,
                    'description'            => $estimateLine->description,
                    'component_code_id'      => $estimateLine->component_code_id,
                    'damage_code_id'         => $estimateLine->damage_code_id,
                    'repair_code_id'         => $estimateLine->repair_code_id,
                    'std_labor_hours'        => $estimateLine->std_labor_hours,
                    'actual_labor_hours'     => null,
                    'labor_rate'             => $estimateLine->labor_rate,
                    'material_qty'           => $estimateLine->material_qty,
                    'material_rate'          => $estimateLine->material_rate,
                    'ancillary'              => $estimateLine->ancillary,
                    'actual_cost'            => null,
                    'status'                 => 'pending',
                    'notes'                  => null,
                ]);
            }

            $this->command->line("  Created work order {$woNo} from estimate {$estimate->estimate_no}");
        }

        $this->command->info('Created ' . ($woCounter - 1) . ' work orders from ' . $approvedEstimates->count() . ' approved estimates.');
    }
}
