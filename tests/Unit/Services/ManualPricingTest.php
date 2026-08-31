<?php

namespace Tests\Unit\Services;

use App\Services\Billing\ManualPricing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic behind a hand-priced storage & handling bill.
 *
 * Three parties compute these numbers — `preview()`, the browser as the operator
 * types, and `store()` on save — and if they disagree the operator sees one total
 * while the customer is billed another. The rules therefore live in one class,
 * and this is where they are pinned down: no database, no application, no
 * request, because the arithmetic is the design and it should be testable as
 * arithmetic.
 *
 * The rule most worth protecting is cumulative free time. Free days are spent
 * from the container's original gate-in, not granted afresh each period. Granting
 * them per period would hand a monthly-billed customer their free days twelve
 * times a year — a revenue leak that no screen would ever show as an error.
 */
class ManualPricingTest extends TestCase
{
    // ── Free days ────────────────────────────────────────────────────────────

    #[DataProvider('freeDayCases')]
    public function test_free_days_in_period(int $header, int $before, int $total, int $expected, string $because): void
    {
        $this->assertSame($expected, ManualPricing::freeDaysInPeriod($header, $before, $total), $because);
    }

    public static function freeDayCases(): array
    {
        return [
            'a fresh container gets its whole allowance' => [
                5, 0, 30, 5,
                'Gated in on the first day of the period: nothing has been consumed yet.',
            ],
            'an allowance already spent gives nothing' => [
                5, 31, 28, 0,
                'Gated in on 1 January with five free days, billed for February — all five went in January. '
                . 'This is the case that makes the rule cumulative rather than flat.',
            ],
            'an allowance partly spent gives the balance' => [
                5, 3, 28, 2,
                'Three days elapsed before the period started, so two remain.',
            ],
            'the balance is capped at the days on the bill' => [
                30, 0, 7, 7,
                'A week-long period cannot consume thirty days of allowance.',
            ],
            'exactly consumed leaves nothing over' => [
                5, 5, 28, 0,
                'The boundary: the last free day was the day before this period opened.',
            ],
            'one day left is one day given' => [
                5, 4, 28, 1,
                'Off-by-one on the other side of the same boundary.',
            ],
            'no free time means none' => [
                0, 0, 30, 0,
                'The default. Manual bills start at zero free time deliberately.',
            ],
            'an empty window consumes nothing' => [
                7, 0, 0, 0,
                'A record closed before the period opened accrues no storage, so it spends no allowance either.',
            ],
            'a negative elapsed count cannot create allowance' => [
                5, -3, 30, 5,
                'Guarded rather than trusted: a gate-in dated after the period start must not grant extra days.',
            ],
            'a negative day count cannot go below zero' => [
                5, 0, -2, 0,
                'Same defensiveness at the other end.',
            ],
        ];
    }

    /**
     * The two halves of the split must always add back up to the whole.
     *
     * Stated as an invariant rather than as examples, because this is what a
     * reviewer actually needs to be true: no day is billed twice and none goes
     * missing.
     */
    public function test_free_and_chargeable_days_always_partition_the_period(): void
    {
        foreach ([0, 1, 5, 30, 400] as $header) {
            foreach ([0, 1, 4, 5, 29, 500] as $before) {
                foreach ([0, 1, 7, 28, 31] as $total) {
                    $free = ManualPricing::freeDaysInPeriod($header, $before, $total);
                    $chg  = ManualPricing::chargeableDays($header, $before, $total);

                    $this->assertSame($total, $free + $chg,
                        "free({$free}) + chargeable({$chg}) must equal the period ({$total}) "
                        . "for header={$header}, before={$before}");
                    $this->assertGreaterThanOrEqual(0, $free);
                    $this->assertGreaterThanOrEqual(0, $chg);
                }
            }
        }
    }

    /** More free time can never bill more days. */
    public function test_raising_the_free_time_never_raises_the_charge(): void
    {
        $previous = PHP_INT_MAX;

        for ($header = 0; $header <= 40; $header++) {
            $chargeable = ManualPricing::chargeableDays($header, 3, 30);
            $this->assertLessThanOrEqual($previous, $chargeable,
                "Going from {$header} free days to " . ($header + 1) . ' must not increase what is billed.');
            $previous = $chargeable;
        }

        $this->assertSame(0, $previous, 'Enough free time eventually bills nothing.');
    }

    /**
     * Each line moves by its own remaining balance, not by a flat number.
     *
     * This is the property the manual screen depends on when the operator edits
     * the header free time: two containers with the same period but different
     * histories must not move together.
     */
    public function test_the_same_free_time_moves_two_containers_differently(): void
    {
        // Same 30-day period. One arrived with the period; one has been in the
        // yard for two months already.
        $fresh = ManualPricing::chargeableDays(10, 0, 30);
        $old   = ManualPricing::chargeableDays(10, 60, 30);

        $this->assertSame(20, $fresh, 'The new arrival spends its whole allowance here.');
        $this->assertSame(30, $old, 'The long-stayer spent its allowance months ago and is billed in full.');
    }

    // ── Tax and totals ───────────────────────────────────────────────────────

    public function test_vat_compounds_on_sscl(): void
    {
        $a = ManualPricing::lineAmounts(1000.0, 0.0, 2.5, 18.0, 0.0, 0.0);

        $this->assertSame(25.0, $a['storage_sscl']);
        $this->assertSame(184.5, $a['storage_vat'], 'VAT is charged on 1025, not on 1000 — matching the tariff flow.');
        $this->assertSame(1209.5, $a['line_grand_total']);
    }

    /**
     * Storage and handling carry different charge codes, so they may carry
     * different tax codes. Summing first and taxing once would apply one code's
     * rates to the other's money.
     */
    public function test_each_portion_is_taxed_with_its_own_rates(): void
    {
        $a = ManualPricing::lineAmounts(
            storageSubtotal: 1000.0,
            handlingSubtotal: 1000.0,
            storageTax1: 0.0,  storageTax2: 18.0,
            handlingTax1: 0.0, handlingTax2: 0.0,
        );

        $this->assertSame(180.0, $a['storage_vat']);
        $this->assertSame(0.0, $a['handling_vat'], 'The handling half is exempt and must stay exempt.');
        $this->assertSame(180.0, $a['line_vat']);
        $this->assertSame(2180.0, $a['line_grand_total']);

        // The counter-check: taxing the combined subtotal would have produced 360.
        $this->assertNotSame(360.0, $a['line_vat'],
            'Taxing the sum once is the mistake this split exists to prevent.');
    }

    public function test_a_tax_exempt_line_is_just_its_subtotals(): void
    {
        $a = ManualPricing::lineAmounts(750.0, 250.0, 0.0, 0.0, 0.0, 0.0);

        $this->assertSame(1000.0, $a['line_total']);
        $this->assertSame(0.0, $a['line_sscl']);
        $this->assertSame(0.0, $a['line_vat']);
        $this->assertSame(1000.0, $a['line_grand_total']);
    }

    public function test_a_zero_line_stays_zero(): void
    {
        $a = ManualPricing::lineAmounts(0.0, 0.0, 2.5, 18.0, 2.5, 18.0);

        $this->assertSame(0.0, $a['line_total']);
        $this->assertSame(0.0, $a['line_grand_total'],
            'A container inside its free time with no lift event contributes nothing.');
    }

    public function test_amounts_are_rounded_to_the_cent(): void
    {
        $a = ManualPricing::lineAmounts(333.335, 0.0, 2.5, 18.0, 0.0, 0.0);

        foreach (['storage_sscl', 'storage_vat', 'line_total', 'line_sscl', 'line_vat', 'line_grand_total'] as $key) {
            $this->assertSame(round($a[$key], 2), $a[$key], "{$key} carries sub-cent precision.");
        }
    }

    // ── The matrix key ───────────────────────────────────────────────────────

    /**
     * The key groups lines into rate-matrix rows. Both ends must agree on it or
     * a typed rate fills the wrong lines — so it is one function, called on the
     * server and echoed to the browser rather than recomputed there.
     */
    public function test_matrix_key_groups_by_equipment_and_size(): void
    {
        $this->assertSame(
            ManualPricing::matrixKey('20GP', '20'),
            ManualPricing::matrixKey('20GP', '20'),
            'Same combination, same row.'
        );

        $this->assertNotSame(
            ManualPricing::matrixKey('20GP', '20'),
            ManualPricing::matrixKey('40HC', '40'),
            'Different equipment gets its own rate.'
        );

        $this->assertNotSame(
            ManualPricing::matrixKey('40HC', '40'),
            ManualPricing::matrixKey('40HC', '20'),
            'Size is part of the identity even when the equipment code is not.'
        );
    }

    public function test_matrix_key_survives_missing_values(): void
    {
        $this->assertSame('—|—', ManualPricing::matrixKey(null, null),
            'A container with no equipment record still needs a row to be priced in.');
        $this->assertSame('20GP|—', ManualPricing::matrixKey('20GP', ''),
            'Empty and null are the same absence.');
    }
}
