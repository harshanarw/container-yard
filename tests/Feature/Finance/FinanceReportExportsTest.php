<?php

namespace Tests\Feature\Finance;

use App\Support\Export\TabularExport;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Exports for the flat finance reports (Phase 4a) and the two statements (4b).
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
     * All but the account ledger, which is absent on purpose: it refuses
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
            'income statement' => ['finance.reports.income-statement.export', 'finance.gl.view', []],
            'balance sheet'    => ['finance.reports.balance-sheet.export',  'finance.gl.view', []],
            'VAT/SSCL return'  => ['finance.reports.vat-sscl-return.export', 'finance.gl.view', []],
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

    // ── Statements (Phase 4b) ────────────────────────────────────────────────

    /** @return array<string,array{0:string,1:string}> */
    public static function statements(): array
    {
        return [
            'customer statement' => ['finance.reports.customer-statement.export', 'finance.ar.view'],
            'supplier statement' => ['finance.reports.supplier-statement.export', 'finance.ap.view'],
        ];
    }

    /**
     * A statement is about one party, so the export insists on one rather than
     * quietly returning everybody's or nobody's.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('statements')]
    public function test_a_statement_export_requires_a_party(string $route, string $permission): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route($route))->assertSessionHasErrors('party_id');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('statements')]
    public function test_a_statement_export_is_refused_without_the_permission(string $route, string $permission): void
    {
        $this->actingAsRole('gate_officer');

        $party = \App\Models\Customer::factory()->create();

        $this->get(route($route, ['party_id' => $party->id]))->assertForbidden();
    }

    /**
     * Opening and closing bracket the rows.
     *
     * They are context rather than transactions, but a statement cannot be
     * reconciled without them — which is exactly why a printed one carries them
     * too.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('statements')]
    public function test_a_statement_export_brackets_its_rows_with_balances(string $route, string $permission): void
    {
        $this->actingAsSystemAdmin();

        $party = \App\Models\Customer::factory()->create(['name' => 'Statement Party', 'code' => 'STP']);

        $rows = $this->parse(
            $this->get(route($route, ['party_id' => $party->id]))->assertOk()->streamedContent()
        );

        $labels = collect($rows)->pluck(1);

        $this->assertContains('Opening balance', $labels->all());
        $this->assertContains('Totals', $labels->all());
        $this->assertContains('Closing balance', $labels->all());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('statements')]
    public function test_a_statement_filename_names_the_party(string $route, string $permission): void
    {
        $this->actingAsSystemAdmin();

        $party    = \App\Models\Customer::factory()->create(['code' => 'ACME']);
        $response = $this->get(route($route, ['party_id' => $party->id]))->assertOk();

        $this->assertStringContainsString('ACME', $response->headers->get('content-disposition'),
            'Several statements in a downloads folder are otherwise indistinguishable.');
    }

    /** The file's closing balance is the service's, not a recomputation. */
    public function test_the_customer_statement_closing_balance_matches_the_screen(): void
    {
        $this->actingAsSystemAdmin();

        $party = \App\Models\Customer::factory()->create();
        $query = ['party_id' => $party->id];

        $screen = $this->get(route('finance.reports.customer-statement', $query))->assertOk();
        $rows   = $this->parse(
            $this->get(route('finance.reports.customer-statement.export', $query))->assertOk()->streamedContent()
        );

        $closing = collect($rows)->first(fn ($r) => ($r[1] ?? null) === 'Closing balance');

        $this->assertEqualsWithDelta(
            (float) $screen->viewData('data')['closing'],
            (float) $closing[9],
            0.01,
            'The statement a customer reconciles against must agree with the one on screen.'
        );
    }

    // ── The two statements (Phase 4c) ────────────────────────────────────────

    /**
     * The invariant that makes the flattening trustworthy rather than merely
     * tidy: within a section, the Account rows sum to the Subtotal rows, and
     * those sum to the Total.
     *
     * A statement on screen is a tree, and a spreadsheet is not. Carrying the
     * shape in a Row Type column only helps if the arithmetic survives the
     * flattening — a reader who filters to Account rows and sums them must land
     * on the same figure as the reader who reads the Total row. If a group is
     * ever emitted without its subtotal, or a subtotal without its accounts,
     * this is what notices.
     */
    public function test_the_income_statement_export_sums_accounts_to_subtotals_to_totals(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();
        $this->postSomeRevenueAndExpense();

        $rows = $this->parse(
            $this->get(route('finance.reports.income-statement.export'))->assertOk()->streamedContent()
        );

        foreach (['Income', 'Expenses'] as $section) {
            $this->assertHierarchyBalances($rows, $section);
        }
    }

    public function test_the_balance_sheet_export_sums_accounts_to_subtotals_to_totals(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();
        $this->postSomeRevenueAndExpense();

        $rows = $this->parse(
            $this->get(route('finance.reports.balance-sheet.export'))->assertOk()->streamedContent()
        );

        foreach (['Assets', 'Liabilities', 'Equity'] as $section) {
            $this->assertHierarchyBalances($rows, $section);
        }
    }

    /** The bottom line in the file is the bottom line on the screen. */
    public function test_the_income_statement_export_matches_the_screens_net_profit(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();
        $this->postSomeRevenueAndExpense();

        $screen = $this->get(route('finance.reports.income-statement'))->assertOk();
        $rows   = $this->parse(
            $this->get(route('finance.reports.income-statement.export'))->assertOk()->streamedContent()
        );

        $net = collect($rows)->first(fn ($r) => ($r[0] ?? null) === 'Summary' && ($r[2] ?? null) === 'Total');

        $this->assertNotNull($net, 'A P&L without its net line is not a P&L.');
        $this->assertEqualsWithDelta(
            (float) $screen->viewData('netProfit'),
            (float) $net[5],
            0.01
        );
        $this->assertSame(
            $screen->viewData('netProfit') >= 0 ? 'NET PROFIT' : 'NET LOSS',
            $net[4],
            'Profit and loss are not the same word, and the file must not call one the other.'
        );
    }

    /**
     * Current Year Earnings is not an account, so it would be easy to leave out
     * of a file that walks the equity accounts. Leaving it out would make the
     * sheet not balance — silently, since a file has no warning triangle.
     */
    public function test_the_balance_sheet_export_carries_current_year_earnings(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();
        $this->postSomeRevenueAndExpense();

        $screen = $this->get(route('finance.reports.balance-sheet'))->assertOk();
        $rows   = $this->parse(
            $this->get(route('finance.reports.balance-sheet.export'))->assertOk()->streamedContent()
        );

        $earnings = collect($rows)->first(
            fn ($r) => ($r[2] ?? null) === 'Account' && ($r[4] ?? null) === 'Current Year Earnings'
        );

        $this->assertNotNull($earnings, 'The live P&L belongs on the sheet, as it does on screen.');
        $this->assertEqualsWithDelta((float) $screen->viewData('currentYearPL'), (float) $earnings[5], 0.01);

        // And the difference the screen shows as a tick or a triangle, stated.
        $check = collect($rows)->first(fn ($r) => ($r[2] ?? null) === 'Check');
        $this->assertNotNull($check);
        $this->assertEqualsWithDelta((float) $screen->viewData('balanceDiff'), (float) $check[5], 0.01);
    }

    /** The settlement figures are the service's, not a second subtraction. */
    public function test_the_vat_return_export_matches_the_screens_settlement(): void
    {
        $this->actingAsSystemAdmin();

        $screen = $this->get(route('finance.reports.vat-sscl-return'))->assertOk();
        $rows   = $this->parse(
            $this->get(route('finance.reports.vat-sscl-return.export'))->assertOk()->streamedContent()
        );

        $summary = $screen->viewData('data')['summary'];

        $net = collect($rows)->first(
            fn ($r) => ($r[0] ?? null) === 'Summary' && str_starts_with((string) ($r[2] ?? ''), 'Net VAT')
        );
        $this->assertNotNull($net, 'A return is filed on its net figure.');
        $this->assertEqualsWithDelta((float) $summary['net_vat_payable'], (float) $net[6], 0.01);

        $sscl = collect($rows)->first(fn ($r) => ($r[2] ?? null) === 'SSCL Payable');
        $this->assertNotNull($sscl);
        $this->assertEqualsWithDelta((float) $summary['sscl_payable'], (float) $sscl[5], 0.01);

        // Input SSCL is carried but never netted — it sits in the SSCL column as
        // its own line and leaves the payable alone.
        $inputSscl = collect($rows)->first(fn ($r) => str_starts_with((string) ($r[2] ?? ''), 'Input SSCL'));
        $this->assertNotNull($inputSscl, 'Dropping it would hide a real cost from the filer.');
        $this->assertEqualsWithDelta((float) $summary['input_sscl'], (float) $inputSscl[5], 0.01);
        $this->assertEqualsWithDelta(
            (float) $summary['output_sscl'],
            (float) $sscl[5],
            0.01,
            'SSCL payable is the output figure alone; input SSCL must not have been netted off it.'
        );
    }

    /** Section totals in the file are the ones on screen. */
    public function test_the_vat_return_export_totals_its_two_sections(): void
    {
        $this->actingAsSystemAdmin();

        $screen = $this->get(route('finance.reports.vat-sscl-return'))->assertOk();
        $rows   = $this->parse(
            $this->get(route('finance.reports.vat-sscl-return.export'))->assertOk()->streamedContent()
        );
        $data = $screen->viewData('data');

        foreach (['Output' => 'output', 'Input' => 'input'] as $section => $key) {
            $total = collect($rows)->first(
                fn ($r) => ($r[0] ?? null) === $section && ($r[1] ?? null) === 'Section total'
            );

            $this->assertNotNull($total, "The {$section} section must carry its total.");
            $this->assertEqualsWithDelta((float) $data[$key]['taxable'], (float) $total[4], 0.01);
            $this->assertEqualsWithDelta((float) $data[$key]['sscl'], (float) $total[5], 0.01);
            $this->assertEqualsWithDelta((float) $data[$key]['vat'], (float) $total[6], 0.01);

            // Every source line on screen is a line in the file.
            $lines = collect($rows)->filter(
                fn ($r) => ($r[0] ?? null) === $section && ($r[1] ?? null) === 'Line'
            );
            $this->assertCount(count($data[$key]['rows']), $lines);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Asserts the Account → Subtotal → Total arithmetic for one section of a
     * flattened statement.
     */
    private function assertHierarchyBalances(array $rows, string $section): void
    {
        $of = fn (string $type) => collect($rows)
            ->filter(fn ($r) => ($r[0] ?? null) === $section && ($r[2] ?? null) === $type);

        $accounts  = $of('Account');
        $subtotals = $of('Subtotal');
        $total     = $of('Total')->first();

        $this->assertNotNull($total, "Section {$section} must carry a total row.");
        $this->assertSame(
            $subtotals->count(),
            $of('Group')->count(),
            "Every group in {$section} carries a subtotal, including the ones the screen suppresses."
        );

        $this->assertEqualsWithDelta(
            $accounts->sum(fn ($r) => (float) $r[5]),
            $subtotals->sum(fn ($r) => (float) $r[5]),
            0.01,
            "The {$section} account rows must sum to its subtotals."
        );
        $this->assertEqualsWithDelta(
            $subtotals->sum(fn ($r) => (float) $r[5]),
            (float) $total[5],
            0.01,
            "The {$section} subtotals must sum to its total."
        );
    }

    /**
     * Enough posted activity for the statements to have something to add up.
     * Revenue and expense both move, so the net line is a real subtraction
     * rather than a zero that would pass any arithmetic.
     */
    private function postSomeRevenueAndExpense(): void
    {
        $engine  = app(\App\Services\Finance\PostingEngine::class);
        $revenue = \App\Models\Account::where('classification', 'income')->where('is_posting', true)
            ->where('is_active', true)->orderBy('code')->firstOrFail();
        $expense = \App\Models\Account::where('classification', 'expense')->where('is_posting', true)
            ->where('is_active', true)->orderBy('code')->firstOrFail();
        $cash    = \App\Models\Account::where('code', '1011')->firstOrFail();

        foreach ([
            [['account_id' => $cash->id, 'debit' => 1500, 'credit' => 0],
             ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1500]],
            [['account_id' => $expense->id, 'debit' => 400, 'credit' => 0],
             ['account_id' => $cash->id, 'debit' => 0, 'credit' => 400]],
        ] as $lines) {
            $journal = $engine->createJournal([
                'journal_date' => now()->toDateString(),
                'journal_type' => 'journal',
                'narration'    => 'Statement export test',
            ], $lines);
            $engine->postJournal($journal, auth()->id());
        }
    }

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
