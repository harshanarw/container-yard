<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('module_code', 50)->unique();
            $table->string('label', 100);
            $table->string('prefix', 20);
            $table->boolean('use_company_prefix')->default(true);
            $table->char('separator', 1)->default('-');
            // date_format: null = no date, 'Ym' = YYYYMM, 'Y' = YYYY
            $table->string('date_format', 10)->nullable();
            $table->tinyInteger('seq_padding')->unsigned()->default(4);
            $table->enum('reset_period', ['never', 'monthly', 'yearly'])->default('never');
            $table->string('current_period', 10)->default('');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        $this->seed();
    }

    private function seed(): void
    {
        $now    = now();
        $yyyymm = $now->format('Ym');
        $yyyy   = $now->format('Y');

        // Helper: extract max sequence from a table for a given prefix pattern.
        // Uses SUBSTRING_INDEX on the last hyphen-separated segment so it works
        // with both the old format (SBI-202607-0001) and the new (CY-SBI-202607-0001).
        $maxSeq = function (string $table, string $likePattern) use (&$maxSeq): int {
            try {
                return (int) (DB::table($table)
                    ->where('invoice_no', 'like', $likePattern)
                    ->selectRaw('MAX(CAST(SUBSTRING_INDEX(invoice_no, \'-\', -1) AS UNSIGNED)) as n')
                    ->value('n') ?? 0);
            } catch (\Throwable) {
                return 0;
            }
        };

        $maxSeqField = function (string $table, string $field, string $likePattern): int {
            try {
                return (int) (DB::table($table)
                    ->where($field, 'like', $likePattern)
                    ->selectRaw("MAX(CAST(SUBSTRING_INDEX({$field}, '-', -1) AS UNSIGNED)) as n")
                    ->value('n') ?? 0);
            } catch (\Throwable) {
                return 0;
            }
        };

        $sequences = [
            [
                'module_code'        => 'storage_invoice',
                'label'              => 'Storage Invoices',
                'prefix'             => 'SBI',
                'use_company_prefix' => true,
                'date_format'        => 'Ym',
                'seq_padding'        => 4,
                'reset_period'       => 'monthly',
                'current_period'     => $yyyymm,
                'last_number'        => $maxSeq('storage_invoices', "%SBI-{$yyyymm}-%"),
            ],
            [
                'module_code'        => 'storage_handling_invoice',
                'label'              => 'Storage & Handling Invoices',
                'prefix'             => 'SHI',
                'use_company_prefix' => true,
                'date_format'        => 'Ym',
                'seq_padding'        => 4,
                'reset_period'       => 'monthly',
                'current_period'     => $yyyymm,
                'last_number'        => $maxSeq('storage_handling_invoices', "%SHI-{$yyyymm}-%"),
            ],
            [
                'module_code'        => 'repair_invoice',
                'label'              => 'Repair Invoices',
                'prefix'             => 'RI',
                'use_company_prefix' => true,
                'date_format'        => null,
                'seq_padding'        => 6,
                'reset_period'       => 'never',
                'current_period'     => '',
                'last_number'        => $maxSeq('repair_invoices', '%RI-%'),
            ],
            [
                'module_code'        => 'reefer_invoice',
                'label'              => 'Reefer Electricity Invoices',
                'prefix'             => 'REF',
                'use_company_prefix' => true,
                'date_format'        => 'Y',
                'seq_padding'        => 5,
                'reset_period'       => 'yearly',
                'current_period'     => $yyyy,
                'last_number'        => $maxSeq('reefer_electricity_invoices', "%REF-{$yyyy}-%"),
            ],
            [
                'module_code'        => 'estimate',
                'label'              => 'Repair Estimates',
                'prefix'             => 'RE',
                'use_company_prefix' => true,
                'date_format'        => null,
                'seq_padding'        => 4,
                'reset_period'       => 'never',
                'current_period'     => '',
                'last_number'        => (function () {
                    try {
                        return (int) (DB::table('estimates')
                            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(estimate_no, '-', -1) AS UNSIGNED)) as n")
                            ->value('n') ?? 0);
                    } catch (\Throwable) {
                        return 0;
                    }
                })(),
            ],
            [
                'module_code'        => 'survey',
                'label'              => 'Container Surveys',
                'prefix'             => 'SRV',
                'use_company_prefix' => true,
                'date_format'        => null,
                'seq_padding'        => 4,
                'reset_period'       => 'never',
                'current_period'     => '',
                'last_number'        => (function () {
                    try {
                        return (int) (DB::table('inquiries')
                            ->where('inquiry_no', 'like', 'SRV-%')
                            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(inquiry_no, '-', -1) AS UNSIGNED)) as n")
                            ->value('n') ?? 0);
                    } catch (\Throwable) {
                        return 0;
                    }
                })(),
            ],
            [
                'module_code'        => 'inquiry',
                'label'              => 'Inquiries',
                'prefix'             => 'INQ',
                'use_company_prefix' => true,
                'date_format'        => null,
                'seq_padding'        => 4,
                'reset_period'       => 'never',
                'current_period'     => '',
                'last_number'        => (function () {
                    try {
                        return (int) (DB::table('inquiries')
                            ->where('inquiry_no', 'like', 'INQ-%')
                            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(inquiry_no, '-', -1) AS UNSIGNED)) as n")
                            ->value('n') ?? 0);
                    } catch (\Throwable) {
                        return 0;
                    }
                })(),
            ],
            [
                'module_code'        => 'work_order',
                'label'              => 'Work Orders',
                'prefix'             => 'WO',
                'use_company_prefix' => true,
                'date_format'        => null,
                'seq_padding'        => 4,
                'reset_period'       => 'never',
                'current_period'     => '',
                'last_number'        => $maxSeqField('work_orders', 'wo_no', 'WO-%'),
            ],
            [
                'module_code'        => 'receipt',
                'label'              => 'AR Receipts',
                'prefix'             => 'RCP',
                'use_company_prefix' => true,
                'date_format'        => null,
                'seq_padding'        => 6,
                'reset_period'       => 'never',
                'current_period'     => '',
                'last_number'        => $maxSeqField('receipts', 'receipt_no', 'RCP-%'),
            ],
            [
                'module_code'        => 'payment_voucher',
                'label'              => 'AP Payment Vouchers',
                'prefix'             => 'PV',
                'use_company_prefix' => true,
                'date_format'        => null,
                'seq_padding'        => 6,
                'reset_period'       => 'never',
                'current_period'     => '',
                'last_number'        => $maxSeqField('payment_vouchers', 'voucher_no', 'PV-%'),
            ],
            [
                'module_code'        => 'supplier_invoice',
                'label'              => 'AP Supplier Invoices',
                'prefix'             => 'SINV',
                'use_company_prefix' => true,
                'date_format'        => null,
                'seq_padding'        => 6,
                'reset_period'       => 'never',
                'current_period'     => '',
                'last_number'        => $maxSeq('supplier_invoices', '%SINV-%'),
            ],
            [
                'module_code'        => 'journal_voucher',
                'label'              => 'GL Journal Vouchers',
                'prefix'             => 'JV',
                'use_company_prefix' => true,
                'date_format'        => null,
                'seq_padding'        => 6,
                'reset_period'       => 'never',
                'current_period'     => '',
                'last_number'        => $maxSeqField('gl_journals', 'journal_no', 'JV-%'),
            ],
            // Gate passes — have prefix fields in CompanySetting but no generator yet;
            // seeded here with counter=0 so they're ready when generation is wired.
            [
                'module_code'        => 'gate_in',
                'label'              => 'Gate-In Passes (EIR)',
                'prefix'             => 'GIN',
                'use_company_prefix' => true,
                'date_format'        => 'Ym',
                'seq_padding'        => 4,
                'reset_period'       => 'monthly',
                'current_period'     => $yyyymm,
                'last_number'        => 0,
            ],
            [
                'module_code'        => 'gate_out',
                'label'              => 'Gate-Out Passes (EIR)',
                'prefix'             => 'GOUT',
                'use_company_prefix' => true,
                'date_format'        => 'Ym',
                'seq_padding'        => 4,
                'reset_period'       => 'monthly',
                'current_period'     => $yyyymm,
                'last_number'        => 0,
            ],
        ];

        $ts = now()->toDateTimeString();
        foreach ($sequences as &$row) {
            $row['is_system']   = true;
            $row['separator']   = '-';
            $row['created_at']  = $ts;
            $row['updated_at']  = $ts;
        }

        DB::table('number_sequences')->insert($sequences);
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
