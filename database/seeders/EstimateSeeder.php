<?php

namespace Database\Seeders;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Seeder;

class EstimateSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::first();

        if (! $admin) {
            $this->command->warn('EstimateSeeder requires at least one user. Run UserSeeder first.');
            return;
        }

        // ── Resolve existing customers ─────────────────────────────────────
        $msk = Customer::where('code', 'MSK')->first();
        $cma = Customer::where('code', 'CMA')->first();
        $pil = Customer::where('code', 'PIL')->first();

        if (! $msk || ! $cma || ! $pil) {
            $this->command->warn('EstimateSeeder requires CustomerSeeder to run first.');
            return;
        }

        // ── Create missing customers (shipping lines from dummy data) ───────
        $msc = Customer::firstOrCreate(['code' => 'MSC'], [
            'name'                => 'MSC Mediterranean Shipping Co.',
            'type'                => 'shipping_line',
            'country'             => 'Sri Lanka',
            'currency'            => 'LKR',
            'payment_terms'       => 'net30',
            'status'              => 'active',
            'email_notifications' => true,
            'auto_invoice'        => false,
        ]);

        $evg = Customer::firstOrCreate(['code' => 'EVG'], [
            'name'                => 'Evergreen Marine Corp.',
            'type'                => 'shipping_line',
            'country'             => 'Sri Lanka',
            'currency'            => 'LKR',
            'payment_terms'       => 'net30',
            'status'              => 'active',
            'email_notifications' => true,
            'auto_invoice'        => false,
        ]);

        $zim = Customer::firstOrCreate(['code' => 'ZIM'], [
            'name'                => 'ZIM Integrated Shipping',
            'type'                => 'shipping_line',
            'country'             => 'Sri Lanka',
            'currency'            => 'LKR',
            'payment_terms'       => 'net30',
            'status'              => 'active',
            'email_notifications' => true,
            'auto_invoice'        => false,
        ]);

        $hlx = Customer::firstOrCreate(['code' => 'HLX'], [
            'name'                => 'Hapag-Lloyd AG',
            'type'                => 'shipping_line',
            'country'             => 'Sri Lanka',
            'currency'            => 'LKR',
            'payment_terms'       => 'net30',
            'status'              => 'active',
            'email_notifications' => true,
            'auto_invoice'        => false,
        ]);

        // ── Ensure required containers exist ───────────────────────────────
        $containerDefs = [
            ['container_no' => 'CMAU9988776', 'size' => '40', 'type_code' => 'GP', 'customer_id' => $cma->id],
            ['container_no' => 'TGHU5551234', 'size' => '20', 'type_code' => 'RF', 'customer_id' => $pil->id],
            ['container_no' => 'MSCU7890123', 'size' => '20', 'type_code' => 'GP', 'customer_id' => $msk->id],
            ['container_no' => 'MSKU2223344', 'size' => '40', 'type_code' => 'HC', 'customer_id' => $msk->id],
            ['container_no' => 'EVGU7654321', 'size' => '40', 'type_code' => 'GP', 'customer_id' => $evg->id],
            ['container_no' => 'ZIMU5432109', 'size' => '40', 'type_code' => 'GP', 'customer_id' => $zim->id],
            ['container_no' => 'HLXU3334455', 'size' => '20', 'type_code' => 'GP', 'customer_id' => $hlx->id],
        ];

        foreach ($containerDefs as $def) {
            Container::firstOrCreate(
                ['container_no' => $def['container_no']],
                array_merge($def, [
                    'condition'    => 'damaged',
                    'cargo_status' => 'empty',
                    'status'       => 'in_repair',
                    'gate_in_date' => now()->subDays(30)->toDateString(),
                    'csc_plate_valid' => true,
                ])
            );
        }

        // ── Resolve inquiries (by inquiry_no) ──────────────────────────────
        $inq = fn (string $no) => Inquiry::where('inquiry_no', $no)->first();

        // ── Estimate definitions (matching the original dummy data) ─────────
        // Amounts use 8% tax throughout.
        // Grand total = subtotal × 1.08 (rounded to 2 dp).
        $estimates = [
            // RE-0044 — CMA CGM | CMAU9988776 | INQ-0089 | Sent | LKR 2,340.00
            [
                'estimate_no'    => 'RE-0044',
                'container_no'   => 'CMAU9988776',
                'customer_id'    => $cma->id,
                'size'           => '40',
                'type_code'      => 'GP',
                'inquiry_no'     => 'INQ-0089',
                'estimate_date'  => '2026-02-26',
                'valid_until'    => '2026-03-27',
                'currency'       => 'LKR',
                'priority'       => 'urgent',
                'status'         => 'sent',
                'sent_at'        => '2026-02-26 09:00:00',
                'send_to_email'  => 'ops@cmacgm.com',
                'tax_percentage' => 8.00,
                'subtotal'       => 2166.67,
                'tax_amount'     => 173.33,
                'grand_total'    => 2340.00,
                'scope_of_work'  => 'Repair right side wall dent. Weld bent corner post. Patch roof puncture near front header.',
                'terms'          => "1. Estimate valid for 30 days.\n2. Prices subject to change based on actual damage.\n3. Payment due within 30 days of invoice.",
                'line_items'     => [
                    ['component' => 'Right Side Wall', 'repair_type' => 'straighten', 'qty' => 2.0,  'unit_price' => 650.00, 'tax_percentage' => 8.00, 'line_amount' => 1300.00],
                    ['component' => 'Corner Post',     'repair_type' => 'weld',       'qty' => 1.0,  'unit_price' => 580.00, 'tax_percentage' => 8.00, 'line_amount' => 580.00],
                    ['component' => 'Roof Panel',      'repair_type' => 'repair',     'qty' => 1.0,  'unit_price' => 460.00, 'tax_percentage' => 8.00, 'line_amount' => 460.00],
                ],
            ],

            // RE-0043 — PIL Shipping | TGHU5551234 | INQ-0088 | Draft | LKR 920.00
            [
                'estimate_no'    => 'RE-0043',
                'container_no'   => 'TGHU5551234',
                'customer_id'    => $pil->id,
                'size'           => '20',
                'type_code'      => 'RF',
                'inquiry_no'     => 'INQ-0088',
                'estimate_date'  => '2026-02-25',
                'valid_until'    => '2026-03-26',
                'currency'       => 'LKR',
                'priority'       => 'critical',
                'status'         => 'draft',
                'sent_at'        => null,
                'send_to_email'  => null,
                'tax_percentage' => 8.00,
                'subtotal'       => 851.85,
                'tax_amount'     => 68.15,
                'grand_total'    => 920.00,
                'scope_of_work'  => 'Replace compressor unit and full refrigeration service. Replace perished door gaskets.',
                'terms'          => "1. Estimate valid for 30 days.\n2. Specialist refrigeration parts may incur additional lead time.\n3. Payment due within 30 days of invoice.",
                'line_items'     => [
                    ['component' => 'Refrigeration Unit (Compressor)', 'repair_type' => 'replace', 'qty' => 1.0, 'unit_price' => 581.85, 'tax_percentage' => 8.00, 'line_amount' => 581.85],
                    ['component' => 'Door Seal Gaskets',               'repair_type' => 'replace', 'qty' => 2.0, 'unit_price' => 135.00, 'tax_percentage' => 8.00, 'line_amount' => 270.00],
                ],
            ],

            // RE-0042 — Maersk Line | MSCU7890123 | INQ-0091 | Approved | LKR 1,058.40
            [
                'estimate_no'    => 'RE-0042',
                'container_no'   => 'MSCU7890123',
                'customer_id'    => $msk->id,
                'size'           => '20',
                'type_code'      => 'GP',
                'inquiry_no'     => 'INQ-0091',
                'estimate_date'  => '2026-02-24',
                'valid_until'    => '2026-03-25',
                'currency'       => 'LKR',
                'priority'       => 'urgent',
                'status'         => 'approved',
                'sent_at'        => '2026-02-24 10:00:00',
                'send_to_email'  => 'ops@maersk.com',
                'approved_by'    => $admin->id,
                'approved_date'  => '2026-02-25 14:00:00',
                'tax_percentage' => 8.00,
                'subtotal'       => 980.00,
                'tax_amount'     => 78.40,
                'grand_total'    => 1058.40,
                'scope_of_work'  => 'Replace 2 damaged floor panels (Section 3 and 4). Replace door seal gasket on both doors. Surface treatment and anti-rust paint to affected areas.',
                'terms'          => "1. Estimate valid for 30 days.\n2. Prices subject to change based on actual damage found during repair.\n3. Additional damages discovered during repair will be notified and re-estimated.\n4. Payment due within 30 days of invoice.",
                'line_items'     => [
                    ['component' => 'Floor Panel',       'repair_type' => 'replace', 'qty' => 2.0, 'unit_price' => 350.00, 'tax_percentage' => 8.00, 'line_amount' => 700.00],
                    ['component' => 'Door Seal Gasket',  'repair_type' => 'replace', 'qty' => 1.0, 'unit_price' => 180.00, 'tax_percentage' => 8.00, 'line_amount' => 180.00],
                    ['component' => 'Right Side Wall',   'repair_type' => 'clean_and_treat', 'qty' => 1.0, 'unit_price' => 100.00, 'tax_percentage' => 8.00, 'line_amount' => 100.00],
                ],
            ],

            // RE-0041 — Maersk (MSK) | MSKU2223344 | INQ-0086 | Sent | LKR 410.40
            [
                'estimate_no'    => 'RE-0041',
                'container_no'   => 'MSKU2223344',
                'customer_id'    => $msk->id,
                'size'           => '40',
                'type_code'      => 'HC',
                'inquiry_no'     => 'INQ-0086',
                'estimate_date'  => '2026-02-23',
                'valid_until'    => '2026-03-24',
                'currency'       => 'LKR',
                'priority'       => 'normal',
                'status'         => 'sent',
                'sent_at'        => '2026-02-23 11:30:00',
                'send_to_email'  => 'ops@maersk.com',
                'tax_percentage' => 8.00,
                'subtotal'       => 380.00,
                'tax_amount'     => 30.40,
                'grand_total'    => 410.40,
                'scope_of_work'  => 'Clean, treat and anti-rust coat both base rails.',
                'terms'          => "1. Estimate valid for 30 days.\n2. Payment due within 30 days of invoice.",
                'line_items'     => [
                    ['component' => 'Base Rail — Anti-Rust Treatment', 'repair_type' => 'clean_and_treat', 'qty' => 2.0, 'unit_price' => 190.00, 'tax_percentage' => 8.00, 'line_amount' => 380.00],
                ],
            ],

            // RE-0040 — Evergreen | EVGU7654321 | no matching inquiry | Approved | LKR 648.00
            [
                'estimate_no'    => 'RE-0040',
                'container_no'   => 'EVGU7654321',
                'customer_id'    => $evg->id,
                'size'           => '40',
                'type_code'      => 'GP',
                'inquiry_no'     => null,
                'estimate_date'  => '2026-02-22',
                'valid_until'    => '2026-03-23',
                'currency'       => 'LKR',
                'priority'       => 'normal',
                'status'         => 'approved',
                'sent_at'        => '2026-02-22 09:00:00',
                'send_to_email'  => 'ops@evergreen.com',
                'approved_by'    => $admin->id,
                'approved_date'  => '2026-02-23 10:00:00',
                'tax_percentage' => 8.00,
                'subtotal'       => 600.00,
                'tax_amount'     => 48.00,
                'grand_total'    => 648.00,
                'scope_of_work'  => 'Replace both door seal gaskets. Repaint affected door surfaces.',
                'terms'          => "1. Estimate valid for 30 days.\n2. Payment due within 30 days of invoice.",
                'line_items'     => [
                    ['component' => 'Door Seal Gaskets', 'repair_type' => 'replace', 'qty' => 2.0, 'unit_price' => 180.00, 'tax_percentage' => 8.00, 'line_amount' => 360.00],
                    ['component' => 'Door Surface',      'repair_type' => 'paint',   'qty' => 1.0, 'unit_price' => 240.00, 'tax_percentage' => 8.00, 'line_amount' => 240.00],
                ],
            ],

            // RE-0039 — ZIM | ZIMU5432109 | no matching inquiry | Completed | LKR 1,620.00
            [
                'estimate_no'    => 'RE-0039',
                'container_no'   => 'ZIMU5432109',
                'customer_id'    => $zim->id,
                'size'           => '40',
                'type_code'      => 'GP',
                'inquiry_no'     => null,
                'estimate_date'  => '2026-02-20',
                'valid_until'    => '2026-03-21',
                'currency'       => 'LKR',
                'priority'       => 'normal',
                'status'         => 'completed',
                'sent_at'        => '2026-02-20 09:00:00',
                'send_to_email'  => 'ops@zim.com',
                'approved_by'    => $admin->id,
                'approved_date'  => '2026-02-21 09:00:00',
                'tax_percentage' => 8.00,
                'subtotal'       => 1500.00,
                'tax_amount'     => 120.00,
                'grand_total'    => 1620.00,
                'scope_of_work'  => 'Repair multiple dents on left side wall. Straighten and weld two bent cross members.',
                'terms'          => "1. Estimate valid for 30 days.\n2. Payment due within 30 days of invoice.",
                'line_items'     => [
                    ['component' => 'Left Side Wall — Dents', 'repair_type' => 'repair',     'qty' => 3.0, 'unit_price' => 300.00, 'tax_percentage' => 8.00, 'line_amount' => 900.00],
                    ['component' => 'Cross Member',            'repair_type' => 'straighten', 'qty' => 2.0, 'unit_price' => 300.00, 'tax_percentage' => 8.00, 'line_amount' => 600.00],
                ],
            ],

            // RE-0038 — Hapag-Lloyd | HLXU3334455 | no inquiry | Rejected | LKR 756.00
            [
                'estimate_no'    => 'RE-0038',
                'container_no'   => 'HLXU3334455',
                'customer_id'    => $hlx->id,
                'size'           => '20',
                'type_code'      => 'GP',
                'inquiry_no'     => null,
                'estimate_date'  => '2026-02-18',
                'valid_until'    => '2026-03-19',
                'currency'       => 'LKR',
                'priority'       => 'normal',
                'status'         => 'rejected',
                'sent_at'        => '2026-02-18 09:00:00',
                'send_to_email'  => 'ops@hapag-lloyd.com',
                'rejected_reason' => 'Customer rejected the estimate citing excessive cost. Will renegotiate floor panel pricing.',
                'tax_percentage' => 8.00,
                'subtotal'       => 700.00,
                'tax_amount'     => 56.00,
                'grand_total'    => 756.00,
                'scope_of_work'  => 'Replace two damaged floor panels.',
                'terms'          => "1. Estimate valid for 30 days.\n2. Payment due within 30 days of invoice.",
                'line_items'     => [
                    ['component' => 'Floor Panel', 'repair_type' => 'replace', 'qty' => 2.0, 'unit_price' => 350.00, 'tax_percentage' => 8.00, 'line_amount' => 700.00],
                ],
            ],
        ];

        // ── Insert estimates ───────────────────────────────────────────────
        foreach ($estimates as $data) {
            if (Estimate::where('estimate_no', $data['estimate_no'])->exists()) {
                $this->command->line("  Skipping {$data['estimate_no']} (already exists).");
                continue;
            }

            $container = Container::where('container_no', $data['container_no'])->firstOrFail();
            $inquiry   = $data['inquiry_no'] ? $inq($data['inquiry_no']) : null;

            $estimate = Estimate::create([
                'estimate_no'     => $data['estimate_no'],
                'inquiry_id'      => $inquiry?->id,
                'container_id'    => $container->id,
                'container_no'    => $data['container_no'],
                'customer_id'     => $data['customer_id'],
                'size'            => $data['size'],
                'type_code'       => $data['type_code'],
                'estimate_date'   => $data['estimate_date'],
                'valid_until'     => $data['valid_until'],
                'currency'        => $data['currency'],
                'priority'        => $data['priority'],
                'status'          => $data['status'],
                'scope_of_work'   => $data['scope_of_work'],
                'terms'           => $data['terms'],
                'tax_percentage'  => $data['tax_percentage'],
                'subtotal'        => $data['subtotal'],
                'tax_amount'      => $data['tax_amount'],
                'grand_total'     => $data['grand_total'],
                'send_to_email'   => $data['send_to_email'] ?? null,
                'send_cc_email'   => null,
                'email_message'   => null,
                'attach_pdf'      => true,
                'attach_photos'   => false,
                'sent_at'         => $data['sent_at'] ?? null,
                'approved_by'     => $data['approved_by'] ?? null,
                'approved_date'   => $data['approved_date'] ?? null,
                'rejected_reason' => $data['rejected_reason'] ?? null,
                'created_by'      => $admin->id,
            ]);

            foreach ($data['line_items'] as $item) {
                $estimate->lineItems()->create($item);
            }

            $this->command->info("  Created {$data['estimate_no']} ({$data['status']}, {$data['currency']} {$data['grand_total']})");
        }
    }
}
