<?php

namespace Tests\Unit\Billing;

use App\Models\StorageMasterDetail;
use App\Services\Tariff\TariffRateGuard;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * A reefer's storage rate is keyed on three things, and the third one bites.
 *
 * `resolve()` matches the arrival's reefer mode, then an "any mode" row, then —
 * only when the arrival recorded no mode — the operating row. Every one of those
 * steps exists for a state live data is in, and together they mean a tariff that
 * predates the column keeps pricing exactly as it did.
 *
 * What they do **not** cover is a tariff whose rows have been made explicit but
 * only in one direction: an `operating` row with no `non_operating` twin. Nothing
 * matches, there is no null row left to fall back to, and a non-operating box
 * resolves to no rate at all. Migration 000307 twins every reefer row that
 * existed when it ran — so the gap is the rows added *since*, and it went from a
 * corner case to the common one when the gate began defaulting an empty reefer
 * to non-operating.
 *
 * The second half is about what the operator is told, which is the part that
 * made this hard to see: the miss used to read "no storage rate line for this
 * equipment type & cargo status" and send them to a tariff screen with a row for
 * that equipment type and cargo status plainly on it.
 */
class ReeferStorageRateResolutionTest extends TestCase
{
    private const EQT = 7;

    /** @param array<int,array{0:?string,1:?string,2:float}> $rows */
    private function tariff(array $rows): Collection
    {
        return new Collection(array_map(function ($row) {
            [$cargo, $mode, $rate] = $row;

            $detail = new StorageMasterDetail();
            $detail->forceFill([
                'equipment_type_id' => self::EQT,
                'cargo_status'      => $cargo,
                'reefer_mode'       => $mode,
                'storage_rate'      => $rate,
                'currency'          => 'USD',
            ]);

            return $detail;
        }, $rows));
    }

    private function rate(Collection $tariff, ?string $cargo, ?string $mode): ?float
    {
        $detail = StorageMasterDetail::resolve($tariff, self::EQT, $cargo, $mode);

        return $detail ? (float) $detail->storage_rate : null;
    }

    // ── A fully specified tariff ─────────────────────────────────────────────

    public function test_each_mode_prices_from_its_own_row(): void
    {
        $tariff = $this->tariff([
            ['empty', 'operating', 10.0],
            ['empty', 'non_operating', 4.0],
            ['laden', 'operating', 20.0],
            ['laden', 'non_operating', 8.0],
        ]);

        $this->assertSame(10.0, $this->rate($tariff, 'empty', 'operating'));
        $this->assertSame(4.0, $this->rate($tariff, 'empty', 'non_operating'));
        $this->assertSame(20.0, $this->rate($tariff, 'laden', 'operating'));
        $this->assertSame(8.0, $this->rate($tariff, 'laden', 'non_operating'));
    }

    /** Recorded before the column existed — read as running, not as nothing. */
    public function test_an_arrival_with_no_mode_prices_as_operating(): void
    {
        $tariff = $this->tariff([
            ['empty', 'operating', 10.0],
            ['empty', 'non_operating', 4.0],
        ]);

        $this->assertSame(10.0, $this->rate($tariff, 'empty', null));
    }

    // ── The gap ──────────────────────────────────────────────────────────────

    /**
     * The live failure, stated plainly. This is what "the storage rate is not
     * picking up on the invoice" looks like from underneath.
     */
    public function test_a_tariff_with_only_an_operating_row_prices_nothing_for_a_nor_box(): void
    {
        $tariff = $this->tariff([
            ['empty', 'operating', 10.0],
            ['laden', 'operating', 20.0],
        ]);

        $this->assertSame(10.0, $this->rate($tariff, 'empty', 'operating'), 'The operating box is fine.');
        $this->assertNull($this->rate($tariff, 'empty', 'non_operating'));
        $this->assertNull($this->rate($tariff, 'laden', 'non_operating'));
    }

    /** And the reverse, for a tariff that only ever priced NORs. */
    public function test_a_tariff_with_only_a_nor_row_prices_nothing_for_an_operating_box(): void
    {
        $tariff = $this->tariff([['empty', 'non_operating', 4.0]]);

        $this->assertNull($this->rate($tariff, 'empty', 'operating'));
    }

    // ── What keeps an un-migrated tariff working ──────────────────────────────

    public function test_a_null_row_prices_both_modes(): void
    {
        $tariff = $this->tariff([['empty', null, 10.0], ['laden', null, 20.0]]);

        $this->assertSame(10.0, $this->rate($tariff, 'empty', 'operating'));
        $this->assertSame(10.0, $this->rate($tariff, 'empty', 'non_operating'));
        $this->assertSame(10.0, $this->rate($tariff, 'empty', null), 'A dry box matches the same row.');
    }

    public function test_the_other_two_dimensions_still_have_to_match(): void
    {
        $tariff = $this->tariff([['laden', 'operating', 20.0]]);

        $this->assertNull($this->rate($tariff, 'empty', 'operating'), 'Wrong cargo status.');
        $this->assertNull(
            StorageMasterDetail::resolve($tariff, 99, 'laden', 'operating'),
            'Wrong equipment type.',
        );
    }

    // ── The message ──────────────────────────────────────────────────────────

    public function test_a_missing_row_names_the_reefer_mode(): void
    {
        $this->assertSame(
            'No storage rate line for this equipment type & cargo status in non-operating (NOR) mode.',
            TariffRateGuard::storageReason(true, 0.0, true, false, 'non_operating'),
        );

        $this->assertSame(
            'No storage rate line for this equipment type & cargo status in operating mode.',
            TariffRateGuard::storageReason(true, 0.0, true, false, 'operating'),
        );
    }

    /** A dry box has no mode, and a message about one would be noise. */
    public function test_a_dry_box_says_nothing_about_reefer_mode(): void
    {
        $this->assertSame(
            'No storage rate line for this equipment type & cargo status.',
            TariffRateGuard::storageReason(true, 0.0, true, false, null),
        );
    }

    public function test_the_other_reasons_outrank_the_mode(): void
    {
        $this->assertSame(
            'No active storage tariff and no stored rate for this container.',
            TariffRateGuard::storageReason(true, 0.0, false, false, 'non_operating'),
        );

        $this->assertSame(
            'Storage rate is set to zero.',
            TariffRateGuard::storageReason(true, 0.0, true, true, 'non_operating'),
            'A row that exists and reads zero is a different problem, and a different fix.',
        );

        $this->assertNull(TariffRateGuard::storageReason(true, 4.0, true, true, 'non_operating'));
        $this->assertNull(TariffRateGuard::storageReason(false, 0.0, true, false, 'non_operating'));
    }
}
