<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\YardJobType;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The Container Inquiry export, after moving it onto the shared exporter.
 *
 * This was the most invasive of the four migrations. It had a nested structure —
 * chunk the gate-ins, then batch-fetch that chunk's gate-outs to avoid an N+1,
 * then pair each movement with its own — and a generator cannot yield from
 * inside `chunk()`'s callback. So the loop became `lazy()->chunk()`, which keeps
 * both the paging and the batching but is a real rewrite rather than a move.
 *
 * These tests exist to pin what that rewrite must not have changed: the
 * headings, the pairing, and that the screen's filters still reach the file.
 */
class ContainerInquiryExportTest extends FeatureTestCase
{
    private Customer $bringer;
    private Customer $other;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-20 12:00:00');
        $this->bringer = Customer::factory()->create(['name' => 'Bringer Lines']);
        $this->other   = Customer::factory()->create(['name' => 'Someone Else Ltd']);
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function gateIn(string $no, ?Customer $customer = null, string $at = '2026-05-01 08:00:00'): Container
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

        $equipment = EquipmentType::all()->first(fn ($e) => ! $e->isReefer())
            ?? EquipmentType::query()->firstOrFail();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => $no,
            'equipment_type_id' => $equipment->id,
            'customer_id'       => ($customer ?? $this->bringer)->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => $at,
        ])->assertSessionHasNoErrors();

        return Container::where('container_no', $no)->firstOrFail();
    }

    private function gateOut(string $no, string $ro, string $at = '2026-05-06 09:00:00'): void
    {
        $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'  => $no,
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => $ro,
            'gate_out_time' => $at,
        ])->assertSessionHasNoErrors();
    }

    /** @return array<int,array<int,string>> parsed rows, heading row first */
    private function export(array $query = []): array
    {
        $csv = $this->get(route('container-inquiry.export', $query))
            ->assertOk()
            ->streamedContent();

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    // ── Framing ──────────────────────────────────────────────────────────────

    public function test_it_downloads_as_a_timestamped_csv(): void
    {
        $this->gateIn('EXPT0000001');

        $response = $this->get(route('container-inquiry.export'))->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertMatchesRegularExpression(
            '/filename=.?container-inquiry-\d{8}-\d{6}\.csv/',
            $response->headers->get('content-disposition'),
            'The filename shape operators already know must survive the migration.'
        );
    }

    public function test_the_heading_row_is_unchanged(): void
    {
        $this->gateIn('EXPT0000002');

        $this->assertSame([
            'EIR Ref', 'Container No', 'Customer', 'Job No', 'Job Type',
            'Gate In', 'Gate Out', 'Days In Yard',
            'Job Status',
            'M&R Status', 'M&R Stage Age (days)', 'Export Ready', 'On Hold',
            'Condition On Arrival', 'Size', 'Cargo Status',
            'Vessel', 'Voyage No', 'BL Number', 'Seal No',
        ], $this->export()[0]);
    }

    // ── Content ──────────────────────────────────────────────────────────────

    public function test_a_container_in_the_yard_is_exported_with_no_gate_out(): void
    {
        $this->gateIn('EXPT0000003');

        $row = $this->rowFor($this->export(), 'EXPT0000003');

        $this->assertSame('Bringer Lines', $row[2]);
        $this->assertSame('2026-05-01 08:00', $row[5], 'Gate In.');
        $this->assertSame('-', $row[6], 'Still here, so no gate-out.');
    }

    /**
     * The pairing the rewrite had to preserve.
     *
     * The gate-out is fetched a chunk at a time and matched by visit, so this is
     * the assertion that would fail if the restructure had broken the batching
     * or the lookup.
     */
    public function test_a_closed_visit_carries_its_own_gate_out_and_day_count(): void
    {
        $this->gateIn('EXPT0000004');
        $this->gateOut('EXPT0000004', 'RO-EXP-4');

        $row = $this->rowFor($this->export(), 'EXPT0000004');

        $this->assertSame('2026-05-01 08:00', $row[5]);
        $this->assertSame('2026-05-06 09:00', $row[6], 'Paired with its own gate-out.');
        $this->assertSame('5', $row[7], 'Five days in yard, counted between the two.');
    }

    public function test_every_gated_in_container_appears_once(): void
    {
        $this->gateIn('EXPT0000005');
        $this->gateIn('EXPT0000006');
        $this->gateIn('EXPT0000007');

        $numbers = collect($this->export())->skip(1)->pluck(1);

        foreach (['EXPT0000005', 'EXPT0000006', 'EXPT0000007'] as $no) {
            $this->assertSame(1, $numbers->filter(fn ($n) => $n === $no)->count(),
                "{$no} should appear exactly once.");
        }
    }

    // ── Filters reach the file ───────────────────────────────────────────────

    /**
     * The bug this kind of feature always has, and it is silent: the operator
     * filters the screen, exports, and gets the unfiltered set.
     */
    public function test_the_screens_filters_are_applied_to_the_export(): void
    {
        $this->gateIn('EXPT0000008', $this->bringer);
        $this->gateIn('EXPT0000009', $this->other);

        $numbers = collect($this->export(['customer_id' => $this->other->id]))->skip(1)->pluck(1);

        $this->assertContains('EXPT0000009', $numbers->all());
        $this->assertNotContains('EXPT0000008', $numbers->all(),
            'Filtered out on screen means filtered out of the file.');
    }

    public function test_a_container_number_filter_narrows_the_file(): void
    {
        $this->gateIn('EXPT0000010');
        $this->gateIn('ZZZZ0000011');

        $numbers = collect($this->export(['container_no' => 'EXPT']))->skip(1)->pluck(1);

        $this->assertContains('EXPT0000010', $numbers->all());
        $this->assertNotContains('ZZZZ0000011', $numbers->all());
    }

    public function test_a_filter_that_matches_nothing_yields_headings_only(): void
    {
        $this->gateIn('EXPT0000012');

        $this->assertCount(1, $this->export(['container_no' => 'NOSUCHBOX']),
            'An empty result is a valid answer: the heading row and nothing else.');
    }

    /** @param array<int,array<int,string>> $rows */
    private function rowFor(array $rows, string $containerNo): array
    {
        foreach (array_slice($rows, 1) as $row) {
            if (($row[1] ?? null) === $containerNo) {
                return $row;
            }
        }

        $this->fail("{$containerNo} was not in the export.");
    }
}
