<?php

namespace Tests\Feature\System;

use App\Models\Container;
use App\Models\Customer;
use App\Models\YardStorage;
use App\Support\Export\TabularExport;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Exports for the three reports that had none: Inventory, Available Stock and
 * the Billing report.
 *
 * Two of them *appeared* to have one. Inventory and Billing both carried an
 * "Export CSV" button pointing at `?export=csv` on their own URL — a query
 * parameter neither controller has ever read, so the page simply reloaded.
 * Operators have had a button that quietly did nothing; these tests are what
 * makes it real.
 *
 * The assertion that matters most on every one of them is that the screen's
 * filters reach the file. That is the bug this kind of feature always has, and
 * nobody notices until a customer is sent somebody else's containers.
 */
class ReportExportsTest extends FeatureTestCase
{
    private Customer $bringer;
    private Customer $other;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 09:00:00');
        $this->bringer = Customer::factory()->create(['name' => 'Bringer Lines', 'code' => 'BRL']);
        $this->other   = Customer::factory()->create(['name' => 'Someone Else Ltd', 'code' => 'SEL']);
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<int,array<int,string>> parsed rows, heading row first */
    private function parse(string $csv): array
    {
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

    private function export(string $route, array $query = []): array
    {
        return $this->parse(
            $this->get(route($route, $query))->assertOk()->streamedContent()
        );
    }

    // ── Inventory ────────────────────────────────────────────────────────────

    public function test_the_inventory_export_carries_the_columns_on_screen(): void
    {
        Container::factory()->create([
            'container_no' => 'INVE0000001',
            'customer_id'  => $this->bringer->id,
            'status'       => 'in_yard',
            'condition'    => 'require_repair',
            'cargo_status' => 'empty',
            'gate_in_date' => '2026-06-10',
        ]);

        $rows = $this->export('reports.inventory.export');

        $this->assertSame([
            'Container No', 'Size', 'Type', 'Customer Code', 'Customer',
            'Condition', 'Cargo', 'Location', 'Gate In Date', 'Days In Yard',
            'Status', 'M&R Status', 'Stage',
        ], $rows[0]);

        $row = $this->rowFor($rows, 'INVE0000001');

        $this->assertSame('BRL', $row[3]);
        $this->assertSame('Bringer Lines', $row[4]);
        $this->assertSame('Require Repair', $row[5],
            'The badge on screen resolves to the words it stands for — a spreadsheet has no colour.');
        $this->assertSame('Empty', $row[6]);
        $this->assertSame('2026-06-10', $row[8]);
        $this->assertSame('5', $row[9], 'Five days in yard, counted to today.');
    }

    public function test_the_inventory_export_honours_the_screens_filters(): void
    {
        Container::factory()->create(['container_no' => 'INVE0000002', 'customer_id' => $this->bringer->id]);
        Container::factory()->create(['container_no' => 'INVE0000003', 'customer_id' => $this->other->id]);

        $numbers = collect($this->export('reports.inventory.export', ['customer_id' => $this->other->id]))
            ->skip(1)->pluck(0);

        $this->assertContains('INVE0000003', $numbers->all());
        $this->assertNotContains('INVE0000002', $numbers->all(),
            'Filtered on screen means filtered in the file — otherwise the operator sends the wrong set.');
    }

    public function test_the_inventory_export_button_is_no_longer_a_dead_link(): void
    {
        $this->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee(route('reports.inventory.export'), false);
    }

    // ── Billing report ───────────────────────────────────────────────────────

    public function test_the_billing_export_carries_closed_stays_with_their_amounts(): void
    {
        $container = Container::factory()->create(['container_no' => 'BILL0000001']);

        YardStorage::create([
            'container_id'    => $container->id,
            'customer_id'     => $this->bringer->id,
            'gate_in_date'    => '2026-06-01',
            'gate_out_date'   => '2026-06-11',
            'total_days'      => 10,
            'free_days'       => 2,
            'chargeable_days' => 8,
            'daily_rate'      => 250,
            'subtotal'        => 2000,
            'tax_amount'      => 360,
            'total_charge'    => 2360,
        ]);

        $rows = $this->export('reports.billing.export');
        $row  = $this->rowFor($rows, 'BILL0000001');

        $this->assertSame('Bringer Lines', $row[1]);
        $this->assertSame('2026-06-01', $row[2]);
        $this->assertSame('2026-06-11', $row[3]);
        $this->assertSame('10', $row[4]);
        $this->assertSame('8', $row[6]);
        $this->assertSame('2360.00', $row[10],
            'Written unformatted so the spreadsheet sums it as a number rather than reading text.');
    }

    public function test_the_billing_export_excludes_open_stays_as_the_screen_does(): void
    {
        $container = Container::factory()->create(['container_no' => 'BILL0000002']);

        YardStorage::create([
            'container_id'  => $container->id,
            'customer_id'   => $this->bringer->id,
            'gate_in_date'  => '2026-06-01',
            'gate_out_date' => null,          // still in the yard
            'total_days'    => 0,
            'free_days'     => 0,
            'daily_rate'    => 0,
        ]);

        $numbers = collect($this->export('reports.billing.export'))->skip(1)->pluck(0);

        $this->assertNotContains('BILL0000002', $numbers->all(),
            'The billing report is about completed stays; the export must agree with it.');
    }

    // ── Available stock ──────────────────────────────────────────────────────

    /**
     * The screen is a roll-up, not a list, so the file is too — one row per
     * size · type · grade rather than one per container.
     */
    public function test_the_stock_export_is_the_roll_up_the_screen_shows(): void
    {
        Container::factory()->count(3)->create([
            'status'       => 'available',
            'size'         => '40',
            'type_code'    => 'HC',
            'customer_id'  => $this->bringer->id,
        ]);

        $rows = $this->export('containers.available-stock.export');

        $this->assertSame([
            'Size · Type · Grade', 'Available', 'Ready', 'Not Ready',
            'On Hold', 'PTI Lapsed',
            'Fresh (≤7d)', 'Aging (8–30d)', 'Stale (>30d)',
            'Avg Days', 'Oldest (days)',
        ], $rows[0]);

        $this->assertGreaterThanOrEqual(2, count($rows), 'At least the headings and one combination.');

        $line = collect($rows)->skip(1)->first(fn ($r) => str_contains($r[0], '40 HC'));
        $this->assertNotNull($line, 'The 40ft high-cube combination should be a row.');
        $this->assertGreaterThanOrEqual(3, (int) $line[1]);
    }

    public function test_the_stock_export_needs_the_same_permission_as_the_screen(): void
    {
        // An inspector holds containers.view and nothing else relevant, so it
        // reaches both. The point is that the export is gated by the same grant
        // as the screen, rather than being open because its button is hidden.
        $this->actingAsRole('inspector');

        $this->get(route('containers.available-stock'))->assertOk();
        $this->get(route('containers.available-stock.export'))->assertOk();
    }

    // ── Shared behaviour ─────────────────────────────────────────────────────

    /** @return array<int,string> */
    public static function exportRoutes(): array
    {
        return [
            'inventory'       => ['reports.inventory.export'],
            'billing'         => ['reports.billing.export'],
            'available stock' => ['containers.available-stock.export'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exportRoutes')]
    public function test_each_export_downloads_as_a_timestamped_csv(string $route): void
    {
        $response = $this->get(route($route))->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertMatchesRegularExpression(
            '/filename=.?[a-z-]+-\d{8}-\d{6}\.csv/',
            $response->headers->get('content-disposition')
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exportRoutes')]
    public function test_each_export_offers_excel_when_the_writer_is_installed(string $route): void
    {
        if (! TabularExport::supports('xlsx')) {
            $this->markTestSkipped('openspout/openspout is not installed.');
        }

        $response = $this->get(route($route, ['format' => 'xlsx']))->assertOk();

        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exportRoutes')]
    public function test_each_export_falls_back_to_csv_for_an_unknown_format(string $route): void
    {
        $response = $this->get(route($route, ['format' => 'nonsense']))->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'),
            'A stale bookmark should still produce the report.');
    }

    /** @param array<int,array<int,string>> $rows */
    private function rowFor(array $rows, string $containerNo): array
    {
        foreach (array_slice($rows, 1) as $row) {
            if (($row[0] ?? null) === $containerNo) {
                return $row;
            }
        }

        $this->fail("{$containerNo} was not in the export.");
    }
}
