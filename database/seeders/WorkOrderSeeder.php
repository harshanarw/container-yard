<?php

namespace Database\Seeders;

use App\Models\Estimate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use Illuminate\Database\Seeder;

class WorkOrderSeeder extends Seeder
{
    public function run(): void
    {
        $approvedEstimates = Estimate::with('lineItems', 'inquiry.container', 'customer')
            ->where('status', 'approved')
            ->get();

        if ($approvedEstimates->isEmpty()) {
            $this->command->warn('No approved estimates found. WorkOrderSeeder skipped.');
            return;
        }

        $supervisor = User::where('role', 'yard_supervisor')->first() ?? User::first();
        $admin      = User::first();

        $counter = 1;

        foreach ($approvedEstimates as $estimate) {
            if (!$estimate->inquiry?->container) {
                continue;
            }

            $container = $estimate->inquiry->container;

            $wo = WorkOrder::create([
                'wo_no'            => 'WO-' . str_pad($counter++, 4, '0', STR_PAD_LEFT),
                'estimate_id'      => $estimate->id,
                'container_id'     => $container->id,
                'container_no'     => $container->container_no,
                'customer_id'      => $estimate->customer_id,
                'assigned_to'      => $supervisor->id,
                'status'           => 'pending',
                'priority'         => 'normal',
                'target_date'      => now()->addDays(7)->toDateString(),
                'started_date'     => null,
                'completed_date'   => null,
                'instructions'     => "Repair work for {$container->container_no} as per estimate {$estimate->estimate_no}.",
                'technician_notes' => null,
                'created_by'       => $admin->id,
                'closed_by'        => null,
            ]);

            foreach ($estimate->lineItems as $line) {
                WorkOrderLine::create([
                    'work_order_id'          => $wo->id,
                    'estimate_line_item_id'  => $line->id,
                    'location_code_id'       => $line->location_code_id,
                    'component_code_id'      => $line->component_code_id,
                    'damage_code_id'         => $line->damage_code_id,
                    'repair_code_id'         => $line->repair_code_id,
                    'cedex_code'             => $line->cedex_code,
                    'qty'                    => $line->qty ?? 1,
                    'status'                 => 'pending',
                    'actual_labor_hours'     => null,
                    'actual_material_qty'    => null,
                    'technician_notes'       => null,
                    'completed_at'           => null,
                    'completed_by'           => null,
                ]);
            }

            $this->command->line("  Created {$wo->wo_no} for {$container->container_no} ({$estimate->lineItems->count()} lines)");
        }

        $this->command->info('Created ' . ($counter - 1) . ' work orders from ' . $approvedEstimates->count() . ' approved estimates.');
    }
}
