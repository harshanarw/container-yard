<?php

namespace Database\Seeders;

use App\Models\MrTariffHeader;
use App\Models\MrTariffItem;
use App\Models\MrTariffSlab;
use Illuminate\Database\Seeder;

/**
 * Seeds slab-based tariff items into the first active MR tariff header.
 * Data derived from the container yard M&R tariff schedule Excel file.
 * Operation types: straight, insert, section, replace, weld, remove, paint, resecure, free
 * Unit types: nos, lift, sqft, inches
 */
class MrTariffItemSeeder extends Seeder
{
    public function run(): void
    {
        // Find or create a default tariff header to attach items to
        $header = MrTariffHeader::where('is_active', true)->first();
        if (!$header) {
            $header = MrTariffHeader::create([
                'name'             => 'Standard M&R Tariff',
                'valid_from'       => now()->startOfYear(),
                'currency'         => 'USD',
                'is_active'        => true,
                'applicable_sizes' => ['20', '40', '45'],
            ]);
        }

        // Wipe existing items for idempotency
        MrTariffItem::where('mr_tariff_header_id', $header->id)->delete();

        $sort = 0;

        foreach ($this->items() as $item) {
            $created = MrTariffItem::create([
                'mr_tariff_header_id' => $header->id,
                'tariff_code'         => $item['code'],
                'operation_type'      => $item['op'],
                'description'         => $item['desc'],
                'unit_type'           => $item['unit'],
                'notes'               => $item['notes'] ?? null,
                'is_active'           => true,
                'sort_order'          => ++$sort,
            ]);

            $slabSort = 0;
            foreach ($item['slabs'] as $slab) {
                MrTariffSlab::create([
                    'mr_tariff_item_id' => $created->id,
                    'slab_label'        => $slab['label'],
                    'qty_from'          => $slab['qty'],
                    'is_additional'     => $slab['add'] ?? false,
                    'labor_hours'       => $slab['labor'],
                    'material_cost'     => $slab['material'],
                    'sort_order'        => ++$slabSort,
                ]);
            }
        }

        $this->command->info("MrTariffItemSeeder: created {$sort} items under tariff header \"{$header->name}\".");
    }

    private function items(): array
    {
        return [
            // ─── STRAIGHT (GS-) ────────────────────────────────────────────────────────

            ['code' => 'GS-01-01', 'op' => 'straight', 'desc' => 'CROSS MEMBER', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.0,  'material' => 45.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 1.5,  'material' => 85.00,  'add' => false],
                ['label' => '3 pcs',           'qty' => 3,   'labor' => 2.0,  'material' => 120.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.5,  'material' => 35.00,  'add' => true],
            ]],

            ['code' => 'GS-01-02', 'op' => 'straight', 'desc' => 'OUTRIGGER', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 0.5,  'material' => 20.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 0.75, 'material' => 36.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 16.00, 'add' => true],
            ]],

            ['code' => 'GS-01-03', 'op' => 'straight', 'desc' => 'FLOOR BOARD', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 1.0,  'material' => 30.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 1.5,  'material' => 55.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 2.5,  'material' => 100.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.5,  'material' => 22.00,  'add' => true],
            ]],

            ['code' => 'GS-02-01', 'op' => 'straight', 'desc' => 'ROOF PANEL', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 1.5,  'material' => 25.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 2.5,  'material' => 45.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 4.0,  'material' => 80.00,  'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.75, 'material' => 18.00,  'add' => true],
            ]],

            ['code' => 'GS-02-02', 'op' => 'straight', 'desc' => 'ROOF BOW', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 0.5,  'material' => 22.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 0.75, 'material' => 40.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 18.00, 'add' => true],
            ]],

            ['code' => 'GS-03-01', 'op' => 'straight', 'desc' => 'SIDE WALL PANEL', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 1.5,  'material' => 28.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 2.5,  'material' => 50.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 4.0,  'material' => 88.00,  'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.75, 'material' => 20.00,  'add' => true],
            ]],

            ['code' => 'GS-04-01', 'op' => 'straight', 'desc' => 'FRONT WALL PANEL', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 2.0,  'material' => 30.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 3.0,  'material' => 55.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 5.0,  'material' => 95.00,  'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.0,  'material' => 22.00,  'add' => true],
            ]],

            ['code' => 'GS-05-01', 'op' => 'straight', 'desc' => 'DOOR PANEL', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 2.0,  'material' => 35.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 3.5,  'material' => 65.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 5.5,  'material' => 110.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.0,  'material' => 25.00,  'add' => true],
            ]],

            ['code' => 'GS-06-01', 'op' => 'straight', 'desc' => 'CORNER POST', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.0,  'material' => 55.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 1.75, 'material' => 100.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.75, 'material' => 45.00,  'add' => true],
            ]],

            ['code' => 'GS-07-01', 'op' => 'straight', 'desc' => 'BASE RAIL', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.5,  'material' => 80.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 2.5,  'material' => 145.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 1.0,  'material' => 65.00,  'add' => true],
            ]],

            // ─── INSERT (IT-) ───────────────────────────────────────────────────────────

            ['code' => 'IT-01-01', 'op' => 'insert', 'desc' => 'CROSS MEMBER INSERT', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 0.75, 'material' => 30.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 1.0,  'material' => 55.00, 'add' => false],
                ['label' => '3 pcs',           'qty' => 3,   'labor' => 1.25, 'material' => 75.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 22.00, 'add' => true],
            ]],

            ['code' => 'IT-01-02', 'op' => 'insert', 'desc' => 'FLOOR BOARD INSERT', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 1.0,  'material' => 25.00, 'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 1.75, 'material' => 45.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 3.0,  'material' => 80.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.5,  'material' => 18.00, 'add' => true],
            ]],

            ['code' => 'IT-02-01', 'op' => 'insert', 'desc' => 'ROOF PANEL INSERT', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 1.25, 'material' => 22.00, 'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 2.0,  'material' => 40.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 3.5,  'material' => 70.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.5,  'material' => 16.00, 'add' => true],
            ]],

            ['code' => 'IT-03-01', 'op' => 'insert', 'desc' => 'SIDE WALL INSERT', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 1.25, 'material' => 24.00, 'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 2.0,  'material' => 44.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 3.5,  'material' => 76.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.5,  'material' => 17.00, 'add' => true],
            ]],

            ['code' => 'IT-05-01', 'op' => 'insert', 'desc' => 'DOOR PANEL INSERT', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 1.5,  'material' => 28.00, 'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 2.5,  'material' => 52.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 4.0,  'material' => 90.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.75, 'material' => 20.00, 'add' => true],
            ]],

            ['code' => 'IT-06-01', 'op' => 'insert', 'desc' => 'CORNER POST INSERT', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.25, 'material' => 65.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 2.0,  'material' => 120.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.75, 'material' => 55.00,  'add' => true],
            ]],

            // ─── SECTION (SN-) ──────────────────────────────────────────────────────────

            ['code' => 'SN-01-01', 'op' => 'section', 'desc' => 'CROSS MEMBER SECTION', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.5,  'material' => 55.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 2.5,  'material' => 100.00, 'add' => false],
                ['label' => '3 pcs',           'qty' => 3,   'labor' => 3.0,  'material' => 140.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.75, 'material' => 40.00,  'add' => true],
            ]],

            ['code' => 'SN-01-02', 'op' => 'section', 'desc' => 'BASE RAIL SECTION', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 2.0,  'material' => 95.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 3.5,  'material' => 175.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 1.5,  'material' => 80.00,  'add' => true],
            ]],

            ['code' => 'SN-03-01', 'op' => 'section', 'desc' => 'SIDE WALL SECTION', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 2.0,  'material' => 35.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 3.5,  'material' => 65.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 6.0,  'material' => 110.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.25, 'material' => 26.00,  'add' => true],
            ]],

            ['code' => 'SN-02-01', 'op' => 'section', 'desc' => 'ROOF SECTION', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 2.0,  'material' => 32.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 3.5,  'material' => 58.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 6.0,  'material' => 100.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.25, 'material' => 24.00,  'add' => true],
            ]],

            ['code' => 'SN-04-01', 'op' => 'section', 'desc' => 'FRONT WALL SECTION', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 2.5,  'material' => 38.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 4.0,  'material' => 70.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 7.0,  'material' => 120.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.5,  'material' => 28.00,  'add' => true],
            ]],

            ['code' => 'SN-05-01', 'op' => 'section', 'desc' => 'DOOR SECTION', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 2.5,  'material' => 42.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 4.0,  'material' => 78.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 7.0,  'material' => 130.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.5,  'material' => 30.00,  'add' => true],
            ]],

            ['code' => 'SN-06-01', 'op' => 'section', 'desc' => 'CORNER POST SECTION', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 2.0,  'material' => 75.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 3.5,  'material' => 135.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 1.5,  'material' => 60.00,  'add' => true],
            ]],

            // ─── REPLACE (RP-) ──────────────────────────────────────────────────────────

            ['code' => 'RP-01-01', 'op' => 'replace', 'desc' => 'CROSS MEMBER REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 2.0,  'material' => 75.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 3.5,  'material' => 140.00, 'add' => false],
                ['label' => '3 pcs',           'qty' => 3,   'labor' => 4.5,  'material' => 195.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 1.0,  'material' => 60.00,  'add' => true],
            ]],

            ['code' => 'RP-01-02', 'op' => 'replace', 'desc' => 'OUTRIGGER REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.0,  'material' => 35.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 1.5,  'material' => 62.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.5,  'material' => 28.00, 'add' => true],
            ]],

            ['code' => 'RP-01-03', 'op' => 'replace', 'desc' => 'FLOOR BOARD REPLACE', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 2.0,  'material' => 50.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 3.5,  'material' => 90.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 6.0,  'material' => 160.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.25, 'material' => 38.00,  'add' => true],
            ]],

            ['code' => 'RP-02-01', 'op' => 'replace', 'desc' => 'ROOF PANEL REPLACE', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 2.5,  'material' => 45.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 4.0,  'material' => 80.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 7.0,  'material' => 135.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.5,  'material' => 32.00,  'add' => true],
            ]],

            ['code' => 'RP-02-02', 'op' => 'replace', 'desc' => 'ROOF BOW REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.0,  'material' => 40.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 1.5,  'material' => 72.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.5,  'material' => 32.00, 'add' => true],
            ]],

            ['code' => 'RP-03-01', 'op' => 'replace', 'desc' => 'SIDE WALL PANEL REPLACE', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 2.5,  'material' => 50.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 4.0,  'material' => 90.00,  'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 7.0,  'material' => 150.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.5,  'material' => 34.00,  'add' => true],
            ]],

            ['code' => 'RP-04-01', 'op' => 'replace', 'desc' => 'FRONT WALL PANEL REPLACE', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 3.0,  'material' => 55.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 5.0,  'material' => 100.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 8.5,  'material' => 165.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.75, 'material' => 38.00,  'add' => true],
            ]],

            ['code' => 'RP-05-01', 'op' => 'replace', 'desc' => 'DOOR PANEL REPLACE', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 3.0,  'material' => 60.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 5.5,  'material' => 110.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 9.0,  'material' => 180.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.75, 'material' => 42.00,  'add' => true],
            ]],

            ['code' => 'RP-05-02', 'op' => 'replace', 'desc' => 'DOOR HINGE REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.0,  'material' => 35.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 1.75, 'material' => 62.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.75, 'material' => 28.00, 'add' => true],
            ]],

            ['code' => 'RP-05-03', 'op' => 'replace', 'desc' => 'LOCKING ROD REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 0.75, 'material' => 25.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 1.25, 'material' => 44.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.5,  'material' => 20.00, 'add' => true],
            ]],

            ['code' => 'RP-05-04', 'op' => 'replace', 'desc' => 'DOOR SEAL REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 seal',          'qty' => 1,   'labor' => 1.0,  'material' => 40.00, 'add' => false],
                ['label' => '2 seals',         'qty' => 2,   'labor' => 1.75, 'material' => 72.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.75, 'material' => 34.00, 'add' => true],
            ]],

            ['code' => 'RP-05-05', 'op' => 'replace', 'desc' => 'DOOR SILL REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.5,  'material' => 60.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 2.5,  'material' => 110.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 1.0,  'material' => 50.00,  'add' => true],
            ]],

            ['code' => 'RP-06-01', 'op' => 'replace', 'desc' => 'CORNER POST REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 3.0,  'material' => 110.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 5.0,  'material' => 200.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 2.0,  'material' => 90.00,  'add' => true],
            ]],

            ['code' => 'RP-07-01', 'op' => 'replace', 'desc' => 'BASE RAIL REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 3.5,  'material' => 130.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 6.0,  'material' => 240.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 2.5,  'material' => 110.00, 'add' => true],
            ]],

            ['code' => 'RP-08-01', 'op' => 'replace', 'desc' => 'REAR SILL REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 2.5,  'material' => 95.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 4.5,  'material' => 175.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 2.0,  'material' => 80.00,  'add' => true],
            ]],

            ['code' => 'RP-09-01', 'op' => 'replace', 'desc' => 'VENT REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 0.5,  'material' => 18.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 0.75, 'material' => 30.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 14.00, 'add' => true],
            ]],

            ['code' => 'RP-10-01', 'op' => 'replace', 'desc' => 'FLOOR PLUG REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 0.25, 'material' => 8.00,  'add' => false],
                ['label' => '2-4 pcs',         'qty' => 2,   'labor' => 0.5,  'material' => 14.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.1,  'material' => 6.00,  'add' => true],
            ]],

            // ─── WELD (WD-) ─────────────────────────────────────────────────────────────

            ['code' => 'WD-01-01', 'op' => 'weld', 'desc' => 'FLOOR WELD - CRACK/HOLE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 spot',          'qty' => 1,   'labor' => 0.5,  'material' => 5.00,  'add' => false],
                ['label' => '2-5 spots',       'qty' => 2,   'labor' => 1.0,  'material' => 8.00,  'add' => false],
                ['label' => '6-10 spots',      'qty' => 6,   'labor' => 1.75, 'material' => 12.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.15, 'material' => 1.50,  'add' => true],
            ]],

            ['code' => 'WD-02-01', 'op' => 'weld', 'desc' => 'ROOF WELD - CRACK/HOLE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 spot',          'qty' => 1,   'labor' => 0.75, 'material' => 6.00,  'add' => false],
                ['label' => '2-5 spots',       'qty' => 2,   'labor' => 1.5,  'material' => 10.00, 'add' => false],
                ['label' => '6-10 spots',      'qty' => 6,   'labor' => 2.5,  'material' => 15.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.2,  'material' => 2.00,  'add' => true],
            ]],

            ['code' => 'WD-03-01', 'op' => 'weld', 'desc' => 'SIDE WALL WELD', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 spot',          'qty' => 1,   'labor' => 0.75, 'material' => 6.00,  'add' => false],
                ['label' => '2-5 spots',       'qty' => 2,   'labor' => 1.5,  'material' => 10.00, 'add' => false],
                ['label' => '6-10 spots',      'qty' => 6,   'labor' => 2.5,  'material' => 14.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.2,  'material' => 2.00,  'add' => true],
            ]],

            ['code' => 'WD-04-01', 'op' => 'weld', 'desc' => 'FRONT WALL WELD', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 spot',          'qty' => 1,   'labor' => 1.0,  'material' => 7.00,  'add' => false],
                ['label' => '2-5 spots',       'qty' => 2,   'labor' => 1.75, 'material' => 12.00, 'add' => false],
                ['label' => '6-10 spots',      'qty' => 6,   'labor' => 3.0,  'material' => 17.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 2.50,  'add' => true],
            ]],

            ['code' => 'WD-05-01', 'op' => 'weld', 'desc' => 'DOOR WELD', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 spot',          'qty' => 1,   'labor' => 1.0,  'material' => 7.00,  'add' => false],
                ['label' => '2-5 spots',       'qty' => 2,   'labor' => 1.75, 'material' => 12.00, 'add' => false],
                ['label' => '6-10 spots',      'qty' => 6,   'labor' => 3.0,  'material' => 17.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 2.50,  'add' => true],
            ]],

            ['code' => 'WD-06-01', 'op' => 'weld', 'desc' => 'CORNER POST WELD', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 spot',          'qty' => 1,   'labor' => 1.0,  'material' => 8.00,  'add' => false],
                ['label' => '2-5 spots',       'qty' => 2,   'labor' => 2.0,  'material' => 14.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.5,  'material' => 4.00,  'add' => true],
            ]],

            ['code' => 'WD-07-01', 'op' => 'weld', 'desc' => 'BASE RAIL WELD', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 spot',          'qty' => 1,   'labor' => 1.25, 'material' => 10.00, 'add' => false],
                ['label' => '2-5 spots',       'qty' => 2,   'labor' => 2.5,  'material' => 18.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.5,  'material' => 5.00,  'add' => true],
            ]],

            ['code' => 'WD-08-01', 'op' => 'weld', 'desc' => 'FORK POCKET WELD', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 spot',          'qty' => 1,   'labor' => 1.0,  'material' => 9.00,  'add' => false],
                ['label' => '2-5 spots',       'qty' => 2,   'labor' => 2.0,  'material' => 16.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.5,  'material' => 5.00,  'add' => true],
            ]],

            // ─── REMOVE (RM-) ───────────────────────────────────────────────────────────

            ['code' => 'RM-01-01', 'op' => 'remove', 'desc' => 'LASHING RING REMOVE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-5 pcs',         'qty' => 1,   'labor' => 0.5,  'material' => 0.00,  'add' => false],
                ['label' => '6-12 pcs',        'qty' => 6,   'labor' => 1.0,  'material' => 0.00,  'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.1,  'material' => 0.00,  'add' => true],
            ]],

            ['code' => 'RM-01-02', 'op' => 'remove', 'desc' => 'FLOOR FITTING REMOVE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-5 pcs',         'qty' => 1,   'labor' => 0.5,  'material' => 0.00,  'add' => false],
                ['label' => '6-12 pcs',        'qty' => 6,   'labor' => 0.75, 'material' => 0.00,  'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.1,  'material' => 0.00,  'add' => true],
            ]],

            ['code' => 'RM-02-01', 'op' => 'remove', 'desc' => 'ROOF FITTING REMOVE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-5 pcs',         'qty' => 1,   'labor' => 0.5,  'material' => 0.00,  'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.1,  'material' => 0.00,  'add' => true],
            ]],

            ['code' => 'RM-05-01', 'op' => 'remove', 'desc' => 'DOOR FITTING REMOVE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-5 pcs',         'qty' => 1,   'labor' => 0.5,  'material' => 0.00,  'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.1,  'material' => 0.00,  'add' => true],
            ]],

            // ─── PAINT (PS-) ────────────────────────────────────────────────────────────

            ['code' => 'PS-01-01', 'op' => 'paint', 'desc' => 'FLOOR - SPOT PAINT', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 0.5,  'material' => 8.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 0.75, 'material' => 14.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 1.25, 'material' => 24.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.25, 'material' => 6.00,  'add' => true],
            ]],

            ['code' => 'PS-02-01', 'op' => 'paint', 'desc' => 'ROOF - SPOT PAINT', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 0.5,  'material' => 8.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 0.75, 'material' => 14.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 1.25, 'material' => 24.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.25, 'material' => 6.00,  'add' => true],
            ]],

            ['code' => 'PS-03-01', 'op' => 'paint', 'desc' => 'SIDE WALL - SPOT PAINT', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 0.5,  'material' => 8.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 0.75, 'material' => 14.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 1.25, 'material' => 24.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.25, 'material' => 6.00,  'add' => true],
            ]],

            ['code' => 'PS-00-01', 'op' => 'paint', 'desc' => 'FULL CONTAINER EXTERIOR PAINT', 'unit' => 'nos', 'slabs' => [
                ['label' => "20'",             'qty' => 1,   'labor' => 8.0,  'material' => 120.00, 'add' => false],
                ['label' => "40'",             'qty' => 1,   'labor' => 14.0, 'material' => 200.00, 'add' => false],
                ['label' => "45'",             'qty' => 1,   'labor' => 16.0, 'material' => 230.00, 'add' => false],
            ]],

            ['code' => 'PS-00-02', 'op' => 'paint', 'desc' => 'FULL CONTAINER INTERIOR PAINT', 'unit' => 'nos', 'slabs' => [
                ['label' => "20'",             'qty' => 1,   'labor' => 6.0,  'material' => 90.00,  'add' => false],
                ['label' => "40'",             'qty' => 1,   'labor' => 10.0, 'material' => 150.00, 'add' => false],
                ['label' => "45'",             'qty' => 1,   'labor' => 12.0, 'material' => 175.00, 'add' => false],
            ]],

            ['code' => 'PS-00-03', 'op' => 'paint', 'desc' => 'NUMBER STENCIL / MARKING', 'unit' => 'nos', 'slabs' => [
                ['label' => 'Base',            'qty' => 1,   'labor' => 1.0,  'material' => 15.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.5,  'material' => 8.00,  'add' => true],
            ]],

            // ─── RESECURE (RE-) ─────────────────────────────────────────────────────────

            ['code' => 'RE-01-01', 'op' => 'resecure', 'desc' => 'FLOOR BOARD RESECURE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-3 boards',      'qty' => 1,   'labor' => 0.5,  'material' => 3.00, 'add' => false],
                ['label' => '4-8 boards',      'qty' => 4,   'labor' => 1.0,  'material' => 5.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.15, 'material' => 1.00, 'add' => true],
            ]],

            ['code' => 'RE-02-01', 'op' => 'resecure', 'desc' => 'ROOF BOW RESECURE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-3 bows',        'qty' => 1,   'labor' => 0.5,  'material' => 3.00, 'add' => false],
                ['label' => '4-8 bows',        'qty' => 4,   'labor' => 1.0,  'material' => 5.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.15, 'material' => 1.00, 'add' => true],
            ]],

            ['code' => 'RE-03-01', 'op' => 'resecure', 'desc' => 'SIDE WALL PANEL RESECURE', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 0.75, 'material' => 4.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 1.25, 'material' => 7.00,  'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.25, 'material' => 2.00,  'add' => true],
            ]],

            ['code' => 'RE-05-01', 'op' => 'resecure', 'desc' => 'LOCKING ROD RESECURE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-2 rods',        'qty' => 1,   'labor' => 0.5,  'material' => 3.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 2.00, 'add' => true],
            ]],

            ['code' => 'RE-05-02', 'op' => 'resecure', 'desc' => 'DOOR HINGE RESECURE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-2 hinges',      'qty' => 1,   'labor' => 0.5,  'material' => 3.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 2.00, 'add' => true],
            ]],

            // ─── FREE (FR-/PT-) ─────────────────────────────────────────────────────────

            ['code' => 'FR-00-01', 'op' => 'free', 'desc' => 'CLEANING - BASIC SWEEP/WASH', 'unit' => 'nos', 'slabs' => [
                ['label' => "20'",             'qty' => 1,   'labor' => 1.0,  'material' => 5.00,  'add' => false],
                ['label' => "40'",             'qty' => 1,   'labor' => 1.5,  'material' => 8.00,  'add' => false],
                ['label' => "45'",             'qty' => 1,   'labor' => 2.0,  'material' => 10.00, 'add' => false],
            ]],

            ['code' => 'FR-00-02', 'op' => 'free', 'desc' => 'CLEANING - FUMIGATION', 'unit' => 'nos', 'slabs' => [
                ['label' => "20'",             'qty' => 1,   'labor' => 0.5,  'material' => 35.00, 'add' => false],
                ['label' => "40'/45'",         'qty' => 1,   'labor' => 0.5,  'material' => 55.00, 'add' => false],
            ]],

            ['code' => 'PT-00-01', 'op' => 'free', 'desc' => 'SURVEY / INSPECTION FEE', 'unit' => 'nos', 'slabs' => [
                ['label' => 'Standard',        'qty' => 1,   'labor' => 1.0,  'material' => 0.00,  'add' => false],
            ]],

            ['code' => 'PT-00-02', 'op' => 'free', 'desc' => 'ADMINISTRATIVE / HANDLING FEE', 'unit' => 'nos', 'slabs' => [
                ['label' => 'Standard',        'qty' => 1,   'labor' => 0.5,  'material' => 10.00, 'add' => false],
            ]],

            ['code' => 'FR-00-03', 'op' => 'free', 'desc' => 'DESICCANT PLACEMENT', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-2 bags',        'qty' => 1,   'labor' => 0.25, 'material' => 12.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.1,  'material' => 5.00,  'add' => true],
            ]],

            ['code' => 'FR-00-04', 'op' => 'free', 'desc' => 'BLOCKING & BRACING', 'unit' => 'lift', 'slabs' => [
                ['label' => 'Base',            'qty' => 1,   'labor' => 2.0,  'material' => 25.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 1.0,  'material' => 10.00, 'add' => true],
            ]],

            // ─── Additional STRAIGHT items ───────────────────────────────────────────────

            ['code' => 'GS-08-01', 'op' => 'straight', 'desc' => 'REAR SILL', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 2.0,  'material' => 90.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 3.5,  'material' => 165.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 1.5,  'material' => 75.00,  'add' => true],
            ]],

            ['code' => 'GS-09-01', 'op' => 'straight', 'desc' => 'FORK POCKET STRAIGHTEN', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.5,  'material' => 20.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 2.5,  'material' => 35.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 1.0,  'material' => 15.00, 'add' => true],
            ]],

            ['code' => 'GS-10-01', 'op' => 'straight', 'desc' => 'LOCKING ROD STRAIGHTEN', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 rod',           'qty' => 1,   'labor' => 0.5,  'material' => 5.00, 'add' => false],
                ['label' => '2-4 rods',        'qty' => 2,   'labor' => 0.75, 'material' => 8.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 3.00, 'add' => true],
            ]],

            ['code' => 'GS-10-02', 'op' => 'straight', 'desc' => 'DOOR HINGE STRAIGHTEN', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 hinge',         'qty' => 1,   'labor' => 0.5,  'material' => 5.00, 'add' => false],
                ['label' => '2-4 hinges',      'qty' => 2,   'labor' => 0.75, 'material' => 8.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 3.00, 'add' => true],
            ]],

            // ─── Additional REPLACE items ────────────────────────────────────────────────

            ['code' => 'RP-11-01', 'op' => 'replace', 'desc' => 'LASHING RING REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-4 pcs',         'qty' => 1,   'labor' => 0.5,  'material' => 8.00,  'add' => false],
                ['label' => '5-10 pcs',        'qty' => 5,   'labor' => 1.0,  'material' => 15.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.1,  'material' => 3.00,  'add' => true],
            ]],

            ['code' => 'RP-12-01', 'op' => 'replace', 'desc' => 'FLOOR DRAIN PLUG REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 0.25, 'material' => 6.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.1,  'material' => 5.00, 'add' => true],
            ]],

            ['code' => 'RP-13-01', 'op' => 'replace', 'desc' => 'CONTAINER HANDLE REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-2 pcs',         'qty' => 1,   'labor' => 0.5,  'material' => 20.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.25, 'material' => 18.00, 'add' => true],
            ]],

            ['code' => 'RP-14-01', 'op' => 'replace', 'desc' => 'CORNER CASTING REPLACE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 3.5,  'material' => 45.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 6.0,  'material' => 82.00,  'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 2.5,  'material' => 38.00,  'add' => true],
            ]],

            // ─── Additional WELD items ───────────────────────────────────────────────────

            ['code' => 'WD-09-01', 'op' => 'weld', 'desc' => 'CORNER CASTING WELD', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 spot',          'qty' => 1,   'labor' => 1.5,  'material' => 12.00, 'add' => false],
                ['label' => '2-4 spots',       'qty' => 2,   'labor' => 2.5,  'material' => 20.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.75, 'material' => 8.00,  'add' => true],
            ]],

            ['code' => 'WD-10-01', 'op' => 'weld', 'desc' => 'LASHING RING WELD', 'unit' => 'nos', 'slabs' => [
                ['label' => '1-5 spots',       'qty' => 1,   'labor' => 0.5,  'material' => 5.00,  'add' => false],
                ['label' => '6-12 spots',      'qty' => 6,   'labor' => 1.25, 'material' => 10.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.15, 'material' => 1.50,  'add' => true],
            ]],

            // ─── INSERT additional items ─────────────────────────────────────────────────

            ['code' => 'IT-07-01', 'op' => 'insert', 'desc' => 'BASE RAIL INSERT', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 2.5,  'material' => 110.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 4.0,  'material' => 200.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 1.5,  'material' => 90.00,  'add' => true],
            ]],

            ['code' => 'IT-08-01', 'op' => 'insert', 'desc' => 'REAR SILL INSERT', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 2.0,  'material' => 80.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 3.5,  'material' => 145.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 1.5,  'material' => 65.00,  'add' => true],
            ]],

            // ─── SECTION additional items ────────────────────────────────────────────────

            ['code' => 'SN-07-01', 'op' => 'section', 'desc' => 'FLOOR BOARD SECTION', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 2.5,  'material' => 60.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 4.0,  'material' => 108.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 7.0,  'material' => 185.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 1.5,  'material' => 45.00,  'add' => true],
            ]],

            ['code' => 'SN-08-01', 'op' => 'section', 'desc' => 'REAR SILL SECTION', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 3.0,  'material' => 120.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 5.0,  'material' => 220.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 2.0,  'material' => 100.00, 'add' => true],
            ]],

            // ─── RESECURE additional items ───────────────────────────────────────────────

            ['code' => 'RE-07-01', 'op' => 'resecure', 'desc' => 'BASE RAIL RESECURE / RE-BOLT', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.0,  'material' => 8.00,  'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 1.75, 'material' => 14.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.5,  'material' => 6.00,  'add' => true],
            ]],

            ['code' => 'RE-08-01', 'op' => 'resecure', 'desc' => 'CORNER POST RESECURE', 'unit' => 'nos', 'slabs' => [
                ['label' => '1 pc',            'qty' => 1,   'labor' => 1.25, 'material' => 10.00, 'add' => false],
                ['label' => '2 pcs',           'qty' => 2,   'labor' => 2.0,  'material' => 18.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 1,   'labor' => 0.75, 'material' => 8.00,  'add' => true],
            ]],

            // ─── PAINT additional items ──────────────────────────────────────────────────

            ['code' => 'PS-04-01', 'op' => 'paint', 'desc' => 'FRONT WALL - SPOT PAINT', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 0.5,  'material' => 9.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 0.75, 'material' => 16.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.25, 'material' => 7.00,  'add' => true],
            ]],

            ['code' => 'PS-05-01', 'op' => 'paint', 'desc' => 'DOOR - SPOT PAINT', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 5 sqft',    'qty' => 5,   'labor' => 0.5,  'material' => 9.00,  'add' => false],
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 0.75, 'material' => 16.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.25, 'material' => 7.00,  'add' => true],
            ]],

            ['code' => 'PS-06-01', 'op' => 'paint', 'desc' => 'UNDERCARRIAGE / BASE RAIL PAINT', 'unit' => 'sqft', 'slabs' => [
                ['label' => 'Up to 10 sqft',   'qty' => 10,  'labor' => 1.0,  'material' => 15.00, 'add' => false],
                ['label' => 'Up to 20 sqft',   'qty' => 20,  'labor' => 1.75, 'material' => 26.00, 'add' => false],
                ['label' => 'Each Additional', 'qty' => 5,   'labor' => 0.25, 'material' => 6.00,  'add' => true],
            ]],
        ];
    }
}
