<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OptionalDemoDataSeeder extends Seeder
{
    /**
     * Seed optional demo/test data for development and testing.
     *
     * This seeder runs all operational/transaction seeders in correct dependency order:
     * 1. GateMovementSeeder    - Gate in/out movements for containers
     * 2. YardStorageSeeder     - Storage calculations from movements
     * 3. StorageInvoiceSeeder  - Storage invoices from storage records
     * 4. StorageHandlingInvoiceSeeder - Handling invoices from movements
     * 5. WorkOrderSeeder       - Work orders from approved estimates
     * 6. RepairInvoiceSeeder   - Repair invoices from work orders
     *
     * Usage:
     *   php artisan db:seed --class=OptionalDemoDataSeeder
     *
     * Or run individual seeders:
     *   php artisan db:seed --class=GateMovementSeeder
     *   php artisan db:seed --class=YardStorageSeeder
     *   etc.
     *
     * These are completely optional and not run by DatabaseSeeder::run()
     * Use these to populate transaction history for testing/demo purposes.
     */
    public function call(array $seeders = []): void
    {
        $this->call([
            GateMovementSeeder::class,
            YardStorageSeeder::class,
            StorageInvoiceSeeder::class,
            StorageHandlingInvoiceSeeder::class,
            WorkOrderSeeder::class,
            RepairInvoiceSeeder::class,
        ]);
    }
}
