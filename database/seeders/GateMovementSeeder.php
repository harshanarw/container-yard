<?php

namespace Database\Seeders;

use App\Models\Container;
use App\Models\GateMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class GateMovementSeeder extends Seeder
{
    /**
     * Seed sample Gate Movement data (demo/test data).
     *
     * Creates realistic gate in/out movement history for sample containers.
     * This is optional test data — not run by default.
     *
     * Usage: php artisan db:seed --class=GateMovementSeeder
     */
    public function run(): void
    {
        $gateOp = User::where('role', 'gate_operator')->first();
        if (!$gateOp) {
            $gateOp = User::first();
        }

        $containers = Container::all();
        if ($containers->isEmpty()) {
            $this->command->warn('No containers found. Run ContainerSeeder first.');
            return;
        }

        $movements = [];

        foreach ($containers as $container) {
            $customer = $container->customer;
            $gateInDate = \Carbon\Carbon::parse($container->gate_in_date);

            // ── Gate In movement ──
            $movements[] = [
                'container_id'      => $container->id,
                'survey_id'         => null,
                'container_no'      => $container->container_no,
                'customer_id'       => $container->customer_id,
                'movement_type'     => 'in',
                'size'              => $container->size,
                'container_type'    => $container->type_code,
                'location_row'      => $container->location_row,
                'location_bay'      => $container->location_bay,
                'location_tier'     => $container->location_tier,
                'location_zone'     => null,
                'condition'         => $container->condition,
                'cargo_status'      => $container->cargo_status,
                'seal_no'           => 'SEAL-' . strtoupper(substr($container->container_no, 0, 4)) . '-' . rand(1000, 9999),
                'vehicle_plate'     => 'KL-' . rand(10, 99) . '-' . chr(65 + rand(0, 25)) . chr(65 + rand(0, 25)) . '-' . rand(1000, 9999),
                'driver_name'       => ['Ahmad Bin Rashid', 'Ravi Kumar', 'David Wong', 'Siti Zahra', 'Mohammed Hassan'][rand(0, 4)],
                'driver_ic'         => '19' . rand(70, 99) . rand(1000, 9999) . '-' . rand(10, 99) . '-' . rand(1000, 9999),
                'release_order'     => null,
                'gate_in_time'      => $gateInDate->setHour(rand(8, 16))->setMinute(rand(0, 59)),
                'gate_out_time'     => null,
                'movement_status'   => 'done',
                'remarks'           => 'Standard gate-in procedure completed',
                'created_by'        => $gateOp->id,
                'created_at'        => $gateInDate->setHour(rand(8, 16))->setMinute(rand(0, 59)),
                'updated_at'        => $gateInDate->setHour(rand(8, 16))->setMinute(rand(0, 59)),
            ];

            // ── Gate Out movement (50% of containers) ──
            if (rand(0, 1) === 1) {
                $gateOutDate = $gateInDate->clone()->addDays(rand(5, 30));

                $movements[] = [
                    'container_id'      => $container->id,
                    'survey_id'         => null,
                    'container_no'      => $container->container_no,
                    'customer_id'       => $container->customer_id,
                    'movement_type'     => 'out',
                    'size'              => $container->size,
                    'container_type'    => $container->type_code,
                    'location_row'      => null,
                    'location_bay'      => null,
                    'location_tier'     => null,
                    'location_zone'     => null,
                    'condition'         => $container->condition,
                    'cargo_status'      => $container->cargo_status,
                    'seal_no'           => 'SEAL-' . strtoupper(substr($container->container_no, 0, 4)) . '-' . rand(1000, 9999),
                    'vehicle_plate'     => 'KL-' . rand(10, 99) . '-' . chr(65 + rand(0, 25)) . chr(65 + rand(0, 25)) . '-' . rand(1000, 9999),
                    'driver_name'       => ['Ahmad Bin Rashid', 'Ravi Kumar', 'David Wong', 'Siti Zahra', 'Mohammed Hassan'][rand(0, 4)],
                    'driver_ic'         => '19' . rand(70, 99) . rand(1000, 9999) . '-' . rand(10, 99) . '-' . rand(1000, 9999),
                    'release_order'     => 'RO-' . strtoupper(substr($container->container_no, 0, 3)) . '-' . rand(100000, 999999),
                    'gate_in_time'      => null,
                    'gate_out_time'     => $gateOutDate->setHour(rand(8, 16))->setMinute(rand(0, 59)),
                    'movement_status'   => 'done',
                    'remarks'           => 'Released on customer request',
                    'created_by'        => $gateOp->id,
                    'created_at'        => $gateOutDate->setHour(rand(8, 16))->setMinute(rand(0, 59)),
                    'updated_at'        => $gateOutDate->setHour(rand(8, 16))->setMinute(rand(0, 59)),
                ];
            }
        }

        foreach ($movements as $movement) {
            GateMovement::create($movement);
        }

        $this->command->info('Created ' . count($movements) . ' gate movements for ' . $containers->count() . ' containers.');
    }
}
