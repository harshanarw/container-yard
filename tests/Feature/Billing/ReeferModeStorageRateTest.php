<?php

namespace Tests\Feature\Billing;

use App\Models\EquipmentType;
use App\Models\StorageMasterDetail;
use App\Models\Customer;
use App\Models\StorageMasterHeader;
use Illuminate\Support\Collection;
use Tests\Support\FeatureTestCase;

/**
 * Storage rates that differ by reefer mode (Phase 2).
 *
 * Nearly all of this is about the fallback chain, because that is where the
 * money is. A tariff that fails to resolve does not throw — it prices the line
 * at zero, and an invoice goes out short with nothing on screen to say why.
 *
 * Three states have to work at once, and they coexist on a live system during a
 * rollout: tariffs nobody has migrated, tariffs the backfill has made explicit,
 * and containers gated in before `gate_movements.reefer_mode` existed.
 */
class ReeferModeStorageRateTest extends FeatureTestCase
{
    private EquipmentType $reefer;
    private EquipmentType $dry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsSystemAdmin();

        $this->reefer = EquipmentType::all()->first(fn ($e) => $e->isReefer())
            ?? $this->fail('No reefer equipment type is seeded.');
        $this->dry = EquipmentType::all()->first(fn ($e) => ! $e->isReefer())
            ?? $this->fail('No dry equipment type is seeded.');
    }

    // ── A tariff nobody has migrated ────────────────────────────────────────

    /**
     * Every rate row still has `reefer_mode = null`. Both modes must resolve to
     * it, or the day this ships every storage line prices at zero.
     */
    public function test_a_legacy_null_row_prices_both_modes(): void
    {
        $rows = $this->rows([
            [$this->reefer->id, 'laden', null, 5.00],
        ]);

        $this->assertSame('5.00', $this->rate($rows, $this->reefer->id, 'laden', 'operating'));
        $this->assertSame('5.00', $this->rate($rows, $this->reefer->id, 'laden', 'non_operating'));
        $this->assertSame('5.00', $this->rate($rows, $this->reefer->id, 'laden', null));
    }

    // ── After the backfill ──────────────────────────────────────────────────

    public function test_each_mode_reads_its_own_row(): void
    {
        $rows = $this->rows([
            [$this->reefer->id, 'laden', 'operating',     5.00],
            [$this->reefer->id, 'laden', 'non_operating', 3.50],
        ]);

        $this->assertSame('5.00', $this->rate($rows, $this->reefer->id, 'laden', 'operating'));
        $this->assertSame('3.50', $this->rate($rows, $this->reefer->id, 'laden', 'non_operating'));
    }

    /**
     * The one the harness caught, and the reason this test file exists.
     *
     * A reefer gated in before Phase 1 carries no mode. On a tariff the backfill
     * has made explicit there is no null row left to fall back to, so without
     * the final step it resolves to nothing and bills at zero — silently, and
     * for exactly the containers that have been in the yard longest.
     */
    public function test_a_container_with_no_recorded_mode_prices_as_operating(): void
    {
        $rows = $this->rows([
            [$this->reefer->id, 'laden', 'operating',     5.00],
            [$this->reefer->id, 'laden', 'non_operating', 3.50],
        ]);

        $this->assertSame('5.00', $this->rate($rows, $this->reefer->id, 'laden', null));
    }

    // ── Dry containers ──────────────────────────────────────────────────────

    public function test_a_dry_container_reads_its_own_null_row(): void
    {
        $rows = $this->rows([
            [$this->dry->id, 'empty', null, 2.00],
        ]);

        $this->assertSame('2.00', $this->rate($rows, $this->dry->id, 'empty', null));
    }

    /**
     * A dry box must never pick up a reefer's operating rate through the final
     * fallback. It cannot in practice — the backfill leaves dry rows alone — but
     * the rows are matched by equipment type first, and this pins that down.
     */
    public function test_a_dry_container_never_takes_a_reefer_row(): void
    {
        $rows = $this->rows([
            [$this->reefer->id, 'empty', 'operating', 9.99],
            [$this->dry->id,    'empty', null,        2.00],
        ]);

        $this->assertSame('2.00', $this->rate($rows, $this->dry->id, 'empty', null));
    }

    // ── Cargo status stays the first key ────────────────────────────────────

    public function test_laden_and_empty_stay_separate_within_a_mode(): void
    {
        $rows = $this->rows([
            [$this->reefer->id, 'laden', 'non_operating', 4.00],
            [$this->reefer->id, 'empty', 'non_operating', 1.00],
        ]);

        $this->assertSame('4.00', $this->rate($rows, $this->reefer->id, 'laden', 'non_operating'));
        $this->assertSame('1.00', $this->rate($rows, $this->reefer->id, 'empty', 'non_operating'));
    }

    /** A genuinely incomplete tariff resolves to nothing rather than a wrong rate. */
    public function test_an_equipment_type_with_no_rows_resolves_to_nothing(): void
    {
        $rows = $this->rows([[$this->reefer->id, 'laden', 'operating', 5.00]]);

        $this->assertNull(StorageMasterDetail::resolve($rows, $this->dry->id, 'laden', null));
    }

    // ── The tariff screen ───────────────────────────────────────────────────

    /**
     * A rate row claiming a reefer mode on a dry equipment type would be
     * unreachable: no dry movement ever carries a mode to match it. The server
     * forces it back to null rather than storing a row that looks configured and
     * prices nothing.
     */
    public function test_a_reefer_mode_on_a_dry_equipment_type_is_discarded(): void
    {
        // No factory for a tariff header, so built directly.
        $header = StorageMasterHeader::create([
            'customer_id'       => Customer::factory()->create()->id,
            'default_free_days' => 7,
            'valid_from'        => now()->startOfYear()->toDateString(),
            'is_active'         => true,
            'created_by'        => auth()->id(),
        ]);

        $this->post(route('masters.storage-tariff.details.store', $header), [
            'equipment_type_id' => $this->dry->id,
            'cargo_status'      => 'empty',
            'reefer_mode'       => 'non_operating',
            'storage_rate'      => 2.00,
            'currency'          => 'USD',
        ]);

        $this->assertNull(
            StorageMasterDetail::where('storage_master_header_id', $header->id)->first()?->reefer_mode
        );
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Rate rows as unsaved models — `resolve()` reads a loaded collection, so
     * these never need to reach the database.
     *
     * @param  array<int,array{0:int,1:string,2:?string,3:float}>  $specs
     */
    private function rows(array $specs): Collection
    {
        return collect($specs)->map(fn ($s) => new StorageMasterDetail([
            'equipment_type_id' => $s[0],
            'cargo_status'      => $s[1],
            'reefer_mode'       => $s[2],
            'storage_rate'      => $s[3],
            'currency'          => 'USD',
        ]));
    }

    private function rate(Collection $rows, int $eqtId, string $cargo, ?string $mode): ?string
    {
        return StorageMasterDetail::resolve($rows, $eqtId, $cargo, $mode)?->storage_rate;
    }
}
