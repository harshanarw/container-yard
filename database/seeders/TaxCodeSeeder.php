<?php

namespace Database\Seeders;

use App\Models\TaxCode;
use Illuminate\Database\Seeder;

class TaxCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            // ── VAT only ────────────────────────────────────────────────────
            [
                'code'        => 'VAT18',
                'description' => 'VAT 18%',
                'tax1_rate'   => 0,
                'tax2_rate'   => 18,
                'is_active'   => true,
                'sort_order'  => 1,
            ],
            [
                'code'        => 'VAT15',
                'description' => 'VAT 15%',
                'tax1_rate'   => 0,
                'tax2_rate'   => 15,
                'is_active'   => true,
                'sort_order'  => 2,
            ],
            // ── VAT + SSCL combinations ──────────────────────────────────────
            [
                'code'        => 'VAT18SSCL25',
                'description' => 'VAT 18% + SSCL 2.5%',
                'tax1_rate'   => 2.5,
                'tax2_rate'   => 18,
                'is_active'   => true,
                'sort_order'  => 3,
            ],
            [
                'code'        => 'VAT15SSCL25',
                'description' => 'VAT 15% + SSCL 2.5%',
                'tax1_rate'   => 2.5,
                'tax2_rate'   => 15,
                'is_active'   => true,
                'sort_order'  => 4,
            ],
            [
                'code'        => 'VAT18SSCL3',
                'description' => 'VAT 18% + SSCL 3%',
                'tax1_rate'   => 3,
                'tax2_rate'   => 18,
                'is_active'   => true,
                'sort_order'  => 5,
            ],
            // ── SSCL only ────────────────────────────────────────────────────
            [
                'code'        => 'SSCL25',
                'description' => 'SSCL 2.5% Only',
                'tax1_rate'   => 2.5,
                'tax2_rate'   => 0,
                'is_active'   => true,
                'sort_order'  => 6,
            ],
            // ── Exempt / Zero-rated ──────────────────────────────────────────
            [
                'code'        => 'VATEX',
                'description' => 'VAT Exempt',
                'tax1_rate'   => 0,
                'tax2_rate'   => 0,
                'is_active'   => true,
                'sort_order'  => 7,
            ],
            [
                'code'        => 'ZERORATED',
                'description' => 'Zero Rated',
                'tax1_rate'   => 0,
                'tax2_rate'   => 0,
                'is_active'   => true,
                'sort_order'  => 8,
            ],
            // ── No tax ──────────────────────────────────────────────────────
            [
                'code'        => 'NOTAX',
                'description' => 'No Tax',
                'tax1_rate'   => 0,
                'tax2_rate'   => 0,
                'is_active'   => true,
                'sort_order'  => 9,
            ],
        ];

        foreach ($codes as $code) {
            TaxCode::updateOrCreate(
                ['code' => $code['code']],
                $code
            );
        }
    }
}
