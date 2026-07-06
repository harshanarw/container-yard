<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reset the system to a clean "go-live" state: wipe all transactional data
 * while keeping master/reference data (customers, users, tariffs, containers,
 * chart of accounts, config, etc.), reset invoice/IRD number sequences, and
 * delete the physical files that belonged to the cleared records.
 *
 * This command NEVER runs with migrations or the normal db:seed. It must be
 * invoked explicitly and confirmed. Always take a full DB + files backup first.
 *
 *   php artisan cyms:reset-transactions --dry-run          # preview only
 *   php artisan cyms:reset-transactions                    # interactive confirm
 *   php artisan cyms:reset-transactions --force            # non-interactive
 *   php artisan cyms:reset-transactions --reset-containers # also clear container in-yard status
 *   php artisan cyms:reset-transactions --keep-audit       # keep audit_logs
 */
class ResetTransactions extends Command
{
    protected $signature = 'cyms:reset-transactions
        {--dry-run   : Show what would be cleared without changing anything}
        {--force     : Skip the interactive confirmation}
        {--keep-audit : Do not clear the audit_logs table}
        {--reset-containers : Also reset movement-derived state — container in-yard status/location/gate dates AND free all yard slots (zone occupancy)}';

    protected $description = 'Wipe all transactional data and reset sequences for a fresh go-live (keeps master data).';

    /**
     * Transactional tables to empty, grouped for readability. Order does not
     * matter — foreign-key checks are disabled during the wipe.
     */
    private array $transactionalTables = [
        // Gate / yard movements
        'gate_movement_photos', 'gate_movements', 'yard_storage', 'yard_jobs',
        'location_adjustments', 'guard_captures', 'container_hires',
        'reefer_plug_sessions', 'reefer_temp_logs',
        // Surveys / inquiries / damage
        'inquiry_photos', 'inquiry_checklists', 'damages', 'inquiries',
        // Estimates / work orders / repair
        'estimate_approval_actions', 'estimate_line_items', 'estimates',
        'work_order_lines', 'work_orders', 'repair_invoice_lines', 'repair_invoices',
        // Invoices (AR/AP) + settlements
        'storage_invoice_details', 'storage_invoices',
        'storage_handling_invoice_lines', 'storage_handling_invoices',
        'reefer_electricity_invoice_lines', 'reefer_electricity_invoices',
        'supplier_invoice_lines', 'supplier_invoices',
        'receipt_allocations', 'receipts',
        'payment_allocations', 'payment_vouchers',
        'ar_credit_note_applications', 'ar_credit_note_lines', 'ar_credit_notes',
        'ap_credit_note_applications', 'ap_credit_note_lines', 'ap_credit_notes',
        // General ledger / posting
        'gl_entries', 'gl_journals', 'invoice_postings',
        // Bank reconciliation (statements + reconciliation sessions)
        'bank_statement_lines', 'bank_reconciliations',
        // Approvals (instances only — workflows are master)
        'approval_actions', 'approval_requests',
        // Misc transactional / system
        'notifications', 'documents', 'portal_tokens',
        'jobs', 'job_batches', 'failed_jobs',
    ];

    /** table => [path columns] — files are removed before the rows are cleared. */
    private array $filePathColumns = [
        'gate_movement_photos' => ['photo_path'],
        'inquiry_photos'       => ['photo_path'],
        'guard_captures'       => ['container_image_path', 'plate_image_path', 'nic_front_path', 'nic_back_path', 'license_front_path', 'license_back_path'],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $tables = $this->transactionalTables;
        if ($this->option('keep-audit') === false) {
            $tables[] = 'audit_logs';
        }
        $tables = array_values(array_filter($tables, fn ($t) => Schema::hasTable($t)));

        $this->line('');
        $this->info('Target: ' . config('app.name') . '  (' . config('app.url') . ')');
        $this->info('DB:     ' . config('database.connections.' . config('database.default') . '.database'));
        $this->line('');

        // ── Preview ──────────────────────────────────────────────────────────
        $this->line('<comment>Tables to CLEAR (rows will be deleted, AUTO_INCREMENT reset):</comment>');
        $total = 0;
        foreach ($tables as $t) {
            $c = (int) DB::table($t)->count();
            $total += $c;
            $this->line(sprintf('  %-38s %8s rows', $t, number_format($c)));
        }
        $this->line(sprintf('  %-38s %8s rows', '(total)', number_format($total)));
        $this->line('');
        $this->line('<comment>Sequences:</comment> number_sequences -> reset to 0 ; ird_invoice_sequences -> emptied');
        if ($this->option('reset-containers')) {
            $this->line('<comment>Containers:</comment> movement-derived status columns will be reset to an empty-yard baseline');
            if (Schema::hasTable('yard_locations')) {
                $occupied = (int) DB::table('yard_locations')->where('status', 'occupied')->count();
                $this->line("<comment>Yard slots:</comment> {$occupied} occupied slot(s) will be freed (zone occupancy reset to empty)");
            }
        } else {
            $this->line('<comment>Containers:</comment> kept as-is (add --reset-containers to also clear stale in-yard status and free yard slots)');
        }
        $this->line('');

        if ($dry) {
            $this->info('Dry run — nothing was changed.');
            return self::SUCCESS;
        }

        // ── Safety confirmation ──────────────────────────────────────────────
        if (! $this->option('force')) {
            $this->error('This PERMANENTLY deletes the data above. Make sure you have a full database + files backup.');
            if (! $this->confirm('Have you taken a backup and do you want to continue?', false)) {
                $this->line('Aborted.');
                return self::SUCCESS;
            }
            if ($this->ask('Type the word RESET to proceed') !== 'RESET') {
                $this->line('Aborted (confirmation phrase did not match).');
                return self::SUCCESS;
            }
        }

        // ── 1. Delete physical files referenced by the tables being cleared ──
        $filesDeleted = $this->deleteFiles();

        // ── 2. Clear the transactional tables (FK checks off) ────────────────
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $t) {
                DB::table($t)->delete();
                // Reset the auto-increment so new records start at 1 (MySQL/MariaDB).
                try { DB::statement("ALTER TABLE `{$t}` AUTO_INCREMENT = 1"); } catch (\Throwable) {}
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // ── 3. Reset number + IRD sequences ──────────────────────────────────
        if (Schema::hasTable('number_sequences')) {
            DB::table('number_sequences')->update(['last_number' => 0, 'current_period' => '']);
        }
        if (Schema::hasTable('ird_invoice_sequences')) {
            DB::table('ird_invoice_sequences')->delete();
        }

        // ── 4. Optional: reset container in-yard status ──────────────────────
        $containersReset = 0;
        if ($this->option('reset-containers') && Schema::hasTable('containers')) {
            // Empty-yard baseline. status/cargo_status/condition are NOT-NULL enums,
            // so they must be set to a valid value (not null): status 'released'
            // (no longer in the yard — the only non in-yard status), cargo 'empty',
            // condition 'sound'. Only the genuinely nullable columns are nulled.
            $baseline = [
                'status'        => 'released',
                'cargo_status'  => 'empty',
                'condition'     => 'sound',
                'location_row'  => null,
                'location_bay'  => null,
                'location_tier' => null,
                'seal_no'       => null,
                'gate_in_date'  => null,
                'gate_out_date' => null,
            ];
            $cols    = Schema::getColumnListing('containers');
            $payload = array_intersect_key($baseline, array_flip($cols));
            if ($payload) {
                $containersReset = DB::table('containers')->update($payload);
            }
        }

        // Free every yard slot the cleared movements had occupied. Zone occupancy
        // is derived from yard_locations.status = 'occupied' (not a stored counter),
        // so the slots must be emptied alongside the container status — otherwise
        // zones keep showing stale occupancy after the reset. yard_locations is a
        // master table (the physical layout), so the rows are kept and only their
        // movement-derived occupancy is cleared.
        $slotsReset = 0;
        if ($this->option('reset-containers') && Schema::hasTable('yard_locations')) {
            $payload = ['status' => 'empty', 'container_id' => null];
            if (Schema::hasColumn('yard_locations', 'last_updated_at')) {
                $payload['last_updated_at'] = null;
            }
            $slotsReset = DB::table('yard_locations')->update($payload);
        }

        $this->line('');
        $this->info('Done.');
        $this->line("  Cleared tables:   " . count($tables));
        $this->line("  Files deleted:    {$filesDeleted}");
        $this->line("  Sequences reset:  number_sequences + ird_invoice_sequences");
        if ($this->option('reset-containers')) {
            $this->line("  Containers reset: {$containersReset}");
            $this->line("  Yard slots freed: {$slotsReset}");
        }
        $this->warn('Recommended: php artisan optimize:clear   (flush any cached counts/config)');

        return self::SUCCESS;
    }

    /** Remove physical files (under public/) referenced by the cleared tables. */
    private function deleteFiles(): int
    {
        $count = 0;
        foreach ($this->filePathColumns as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $cols = array_values(array_intersect($cols, Schema::getColumnListing($table)));
            foreach ($cols as $col) {
                DB::table($table)->whereNotNull($col)->pluck($col)->each(function ($path) use (&$count) {
                    if (! $path) {
                        return;
                    }
                    $full = public_path(ltrim($path, '/'));
                    if (is_file($full)) {
                        @unlink($full);
                        $count++;
                    }
                });
            }
        }
        return $count;
    }
}
