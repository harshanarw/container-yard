<?php

namespace Tests\Feature\Finance;

use App\Support\Export\TabularExport;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Exports for the eight flat finance reports (Phase 4a).
 *
 * Two things are worth testing harder here than on the operational reports.
 *
 * **Authorization.** Every one of these controller actions calls
 * `$this->authorize(...)` inline rather than relying on constructor middleware.
 * An export that forgets the call is simply open — no error, no clue, just a
 * ledger anyone signed in can download. Phase 3 turned that gap up on the stock
 * export, so each of these is checked against a role that should not see it.
 *
 * **Agreement with the screen.** A financial figure that disagrees with the
 * screen is worse than no export, so none of these re-derives anything: each
 * reads the same computed data the view is handed. The trial-balance test
 * asserts that directly, by comparing the file's total row with the view's own
 * totals.
 */
class FinanceReportExportsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Seven of the eight. The account ledger is absent on purpose: it refuses
     * without an `account_id`, so it cannot share the "just hit the route"
     * cases and has its own below.
     *
     * @return array<string,array{0:string,1:string,2:array<string,string>}>
     */
    public static function exports(): array
    {
        return [
            // route name                              permission            extra query
            'GL journals'      => ['finance.gl.journals.export',            'finance.gl.view', []],
            'trial balance'    => ['finance.gl.trial-balance.export',       'finance.gl.view', []],
            'FX gain/loss'     => ['finance.reports.fx-gain-loss.export',   'finance.gl.view', []],
            'FX revaluation'   => ['finance.reports.fx-revaluation.export', 'finance.gl.view', []],
            'WHT report'       => ['finance.reports.wht-report.export',     'finance.gl.view', []],
            'AR aging'         => ['finance.ar.aging.export',               'finance.ar.view', []],
            'AP aging'         => ['finance.ap.aging.export',               'finance.ap.view', []],
        ];
    }

    // ── Framing ──────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('exports')]
    public function test_each_export_downloads_as_a_timestamped_csv(string $route, string $permission, array $query): void
    {
        $this->actingAsSystemAdmin();

        $response = $this->get(route($route, $query))->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertMatchesRegularExpression(
            '/filename=.?[a-z0-9-]+-\d{8}-\d{6}\.csv/',
            $response->headers->get('content-disposition')
        );
    }

    /**
     * An empty period is a valid answer.
     *
     * These run against a freshly seeded database with no journals posted, so
     * every one of them is exercising exactly that: the headings come out and
     * nothing falls over on an empty result.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('exports')]
    public function test_each_export_produces_headings_on_an_empty_period(string $route, string $permission, array $query): void
    {
        $this->actingAsSystemAdmin();

        $rows = $this->parse($this->get(route($route, $query))->assertOk()->streamedContent());

        $this->assertNotEmpty($rows, 'There is always a heading row.');
        $this->assertNotEmpty($rows[0][0] ?? '', 'And it is not blank.');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exports')]
    public function test_each_export_falls_back_to_csv_for_an_unknown_format(string $route, string $permission, array $query): void
    {
        $this->actingAsSystemAdmin();

        $response = $this->get(route($route, array_merge($query, ['format' => 'nonsense'])))->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exports')]
    public function test_each_export_offers_excel_when_the_writer_is_installed(string $route, string $permission, array $query): void
    {
        if (! TabularExport::supports('xlsx')) {
            $this->markTestSkipped('openspout/openspout is not installed.');
        }

        $this->actingAsSystemAdmin();

        $response = $this->get(route($route, array_merge($query, ['format' => 'xlsx'])))->assertOk();

        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'));
    }

    // ── Authorization ────────────────────────────────────────────────────────

    /**
     * A gate officer holds no finance permission at all, so every one of these
     * must refuse. The point is not the role — it is that the export carries a
     * check of its own rather than relying on its button being hidden.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('exports')]
    public function test_each_export_is_refused_without_the_screens_permission(string $route, string $permission, array $query): void
    {
        $this->actingAsRole('gate_officer');

        $this->get(route($route, $query))->assertForbidden();
    }

    // ── Agreement with the screen ────────────────────────────────────────────

    /**
     * The file's total row is the view's own total.
     *
     * This is the assertion the whole phase rests on: the export reads the
     * computed data the screen was handed rather than running the query again.
     * If someone later "optimises" it into a separate query, this is what
     * catches the divergence.
     */
    public function test_the_trial_balance_total_matches_the_screens_total(): void
    {
        $this->actingAsSystemAdmin();

        $screen = $this->get(route('finance.gl.trial-balance'))->assertOk();
        $rows   = $this->parse($this->get(route('finance.gl.trial-balance.export'))->assertOk()->streamedContent());

        $totalRow = collect($rows)->first(fn ($r) => ($r[1] ?? null) === 'TOTAL');
        $this->assertNotNull($totalRow, 'A trial balance is not worth reading without its totals.');

        $this->assertEqualsWithDelta(
            (float) $screen->viewData('totalDebit'),
            (float) $totalRow[3],
            0.01,
            'The exported debit total must be the screen\'s debit total.'
        );
        $this->assertEqualsWithDelta(
            (float) $screen->viewData('totalCredit'),
            (float) $totalRow[4],
            0.01
        );
    }

    /** The row count matches too, not just the totals. */
    public function test_the_trial_balance_row_count_matches_the_screen(): void
    {
        $this->actingAsSystemAdmin();

        $screen = $this->get(route('finance.gl.trial-balance'))->assertOk();
        $rows   = $this->parse($this->get(route('finance.gl.trial-balance.export'))->assertOk()->streamedContent());

        // Headings, one row per account, one total row.
        $this->assertCount(
            count($screen->viewData('rows')) + 2,
            $rows,
            'Every account on screen is a row in the file, and no extras.'
        );
    }

    // ── The account ledger needs an account ──────────────────────────────────

    /**
     * Without one there is no ledger, and a file containing the filter screen's
     * empty state would be a download that explains nothing.
     */
    public function test_the_account_ledger_export_refuses_without_an_account(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('finance.gl.account-ledger.export'))->assertNotFound();
    }

    public function test_the_account_ledger_export_brackets_the_rows_with_balances(): void
    {
        $this->actingAsSystemAdmin();

        $account = \App\Models\Account::where('is_posting', true)->where('is_active', true)->firstOrFail();

        $rows = $this->parse(
            $this->get(route('finance.gl.account-ledger.export', ['account_id' => $account->id]))
                ->assertOk()->streamedContent()
        );

        $labels = collect($rows)->pluck(2);

        $this->assertContains('Opening balance', $labels->all(),
            'A ledger cannot be reconciled without the balance it started from.');
        $this->assertContains('Closing balance', $labels->all());
    }

    public function test_the_account_ledger_filename_names_the_account(): void
    {
        $this->actingAsSystemAdmin();

        $account  = \App\Models\Account::where('is_posting', true)->where('is_active', true)->firstOrFail();
        $response = $this->get(route('finance.gl.account-ledger.export', ['account_id' => $account->id]))->assertOk();

        $this->assertStringContainsString(
            'account-ledger-' . $account->code,
            $response->headers->get('content-disposition'),
            'Three ledgers in a downloads folder are indistinguishable otherwise.'
        );
    }

    // ── The journals export is not the paginated page ────────────────────────

    /**
     * The screen shows thirty at a time. A file containing only the first thirty
     * would be a quiet trap, so the export ignores pagination.
     */
    public function test_the_journals_export_is_not_limited_to_one_page(): void
    {
        $this->actingAsSystemAdmin();

        $screen = $this->get(route('finance.gl.journals.index'))->assertOk();
        $rows   = $this->parse($this->get(route('finance.gl.journals.export'))->assertOk()->streamedContent());

        $this->assertCount(
            $screen->viewData('journals')->total() + 1,   // + the heading row
            $rows,
            'The file carries the whole filtered set, not the page being viewed.'
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array<int,array<int,string>> */
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
}
