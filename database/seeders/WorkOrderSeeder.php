<?php

namespace Database\Seeders;

use App\Models\Estimate;
use App\Models\RepairCategory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Services\RepairCategoryResolver;
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

        if (RepairCategory::count() === 0) {
            $this->command->warn('No repair categories found. WorkOrderSeeder skipped — run RepairCategorySeeder first.');
            return;
        }

        $supervisor = User::where('role', 'yard_supervisor')->first() ?? User::first();
        $admin      = User::first();
        $resolver   = new RepairCategoryResolver();
        $counter    = WorkOrder::count() + 1;
        $totalWOs   = 0;

        foreach ($approvedEstimates as $estimate) {
            if (!$estimate->inquiry?->container) {
                continue;
            }

            $container = $estimate->inquiry->container;

            // Resolve and assign repair categories to estimate line items
            foreach ($estimate->lineItems as $line) {
                if ($line->repair_category_id) {
                    continue; // already assigned
                }
                $cat = $resolver->resolve($line->component_code_id, $line->repair_type);
                if ($cat) {
                    $line->update(['repair_category_id' => $cat->id]);
                }
            }

            // Reload with updated category data
            $estimate->load('lineItems');

            // Group unassigned lines by category
            $linesByCategory = $estimate->lineItems
                ->filter(fn($l) => $l->repair_category_id !== null)
                ->groupBy('repair_category_id');

            if ($linesByCategory->isEmpty()) {
                // Fallback: create a single WO without category if no lines have categories
                $fallbackLines = $estimate->lineItems;
                if ($fallbackLines->isEmpty()) {
                    continue;
                }

                $woNo = 'WO-' . str_pad($counter++, 4, '0', STR_PAD_LEFT);
                $wo   = WorkOrder::create([
                    'wo_no'              => $woNo,
                    'estimate_id'        => $estimate->id,
                    'container_id'       => $container->id,
                    'container_no'       => $container->container_no,
                    'customer_id'        => $estimate->customer_id,
                    'repair_category_id' => null,
                    'assigned_to'        => $supervisor->id,
                    'status'             => 'pending',
                    'priority'           => 'normal',
                    'target_date'        => now()->addDays(7)->toDateString(),
                    'instructions'       => "Repair work for {$container->container_no} — {$estimate->estimate_no}.",
                    'created_by'         => $admin->id,
                ]);

                foreach ($fallbackLines as $line) {
                    WorkOrderLine::create([
                        'work_order_id'         => $wo->id,
                        'estimate_line_item_id'  => $line->id,
                        'location_code_id'       => $line->location_code_id,
                        'component_code_id'      => $line->component_code_id,
                        'damage_code_id'         => $line->damage_code_id,
                        'repair_code_id'         => $line->repair_code_id,
                        'cedex_code'             => $line->cedex_code,
                        'qty'                    => $line->qty ?? 1,
                        'status'                 => 'pending',
                    ]);
                }

                $this->command->line("  Created {$woNo} (no category) for {$container->container_no} ({$fallbackLines->count()} lines)");
                $totalWOs++;
                continue;
            }

            // Create one WO per category
            foreach ($linesByCategory as $categoryId => $lines) {
                $category = RepairCategory::find($categoryId);
                $woNo     = 'WO-' . str_pad($counter++, 4, '0', STR_PAD_LEFT);

                $wo = WorkOrder::create([
                    'wo_no'              => $woNo,
                    'estimate_id'        => $estimate->id,
                    'container_id'       => $container->id,
                    'container_no'       => $container->container_no,
                    'customer_id'        => $estimate->customer_id,
                    'repair_category_id' => $categoryId,
                    'assigned_to'        => $supervisor->id,
                    'status'             => 'pending',
                    'priority'           => 'normal',
                    'target_date'        => now()->addDays(7)->toDateString(),
                    'instructions'       => "Repair work [{$category->code}] for {$container->container_no} — {$estimate->estimate_no}.",
                    'created_by'         => $admin->id,
                ]);

                foreach ($lines as $line) {
                    WorkOrderLine::create([
                        'work_order_id'         => $wo->id,
                        'estimate_line_item_id'  => $line->id,
                        'location_code_id'       => $line->location_code_id,
                        'component_code_id'      => $line->component_code_id,
                        'damage_code_id'         => $line->damage_code_id,
                        'repair_code_id'         => $line->repair_code_id,
                        'cedex_code'             => $line->cedex_code,
                        'qty'                    => $line->qty ?? 1,
                        'status'                 => 'pending',
                    ]);
                }

                $this->command->line("  Created {$woNo} [{$category->code}] for {$container->container_no} ({$lines->count()} lines)");
                $totalWOs++;
            }
        }

        $this->command->info("Created {$totalWOs} work orders from {$approvedEstimates->count()} approved estimates.");
    }
}
