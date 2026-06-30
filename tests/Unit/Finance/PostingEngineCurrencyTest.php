<?php

namespace Tests\Unit\Finance;

use App\Models\CompanySetting;
use App\Services\Finance\PostingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase B: PostingEngine per-line currency normalisation and journal-currency
 * derivation. normalizeLine()/journalCurrency() take the base currency as an
 * argument, so these exercise the exact logic without the full createJournal
 * fixtures (financial year / period / number sequence). Private methods are
 * reached via reflection.
 */
class PostingEngineCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CompanySetting::query()->delete();
        CompanySetting::create(['company_name' => 'Test Co', 'default_currency_code' => 'LKR']);
        Cache::forget('company_settings');
    }

    private function normalize(array $line): array
    {
        $engine = app(PostingEngine::class);
        $m = new \ReflectionMethod($engine, 'normalizeLine');
        $m->setAccessible(true);
        return $m->invoke($engine, $line, 'LKR');
    }

    private function journalCurrency(array $entries): string
    {
        $engine = app(PostingEngine::class);
        $m = new \ReflectionMethod($engine, 'journalCurrency');
        $m->setAccessible(true);
        return $m->invoke($engine, $entries, 'LKR');
    }

    public function test_base_only_line_defaults_to_base_currency_and_txn_equals_base(): void
    {
        $e = $this->normalize(['account_id' => 1, 'debit' => 100, 'credit' => 0]);

        $this->assertSame('LKR', $e['currency']);
        $this->assertSame(1.0, $e['exchange_rate']);
        $this->assertSame(100.0, $e['txn_debit']);
        $this->assertSame(0.0, $e['txn_credit']);
        // group/reporting currency stays unpopulated
        $this->assertNull($e['group_currency']);
    }

    public function test_txn_amount_defaults_to_base_amount_when_not_supplied(): void
    {
        $e = $this->normalize(['account_id' => 1, 'debit' => 0, 'credit' => 42.5]);
        $this->assertSame(42.5, $e['txn_credit']);
        $this->assertSame(0.0, $e['txn_debit']);
    }

    public function test_foreign_line_keeps_supplied_currency_rate_and_txn(): void
    {
        $e = $this->normalize([
            'account_id'    => 1,
            'debit'         => 30000,
            'credit'        => 0,
            'currency'      => 'USD',
            'exchange_rate' => 300,
            'txn_debit'     => 100,
            'txn_credit'    => 0,
        ]);

        $this->assertSame('USD', $e['currency']);
        $this->assertSame(300.0, $e['exchange_rate']);
        $this->assertSame(100.0, $e['txn_debit']);   // original document amount
        $this->assertSame(30000.0, $e['debit']);     // base stays authoritative
    }

    public function test_currency_is_uppercased(): void
    {
        $e = $this->normalize(['account_id' => 1, 'debit' => 1, 'credit' => 0, 'currency' => 'usd', 'exchange_rate' => 2]);
        $this->assertSame('USD', $e['currency']);
    }

    public function test_journal_currency_is_the_single_foreign_currency_ignoring_base_fx_leg(): void
    {
        // A foreign receipt: foreign bank + foreign AR + base-currency FX leg.
        $currency = $this->journalCurrency([
            ['currency' => 'USD'],
            ['currency' => 'USD'],
            ['currency' => 'LKR'],
        ]);
        $this->assertSame('USD', $currency);
    }

    public function test_journal_currency_is_base_when_all_lines_are_base(): void
    {
        $this->assertSame('LKR', $this->journalCurrency([
            ['currency' => 'LKR'],
            ['currency' => 'LKR'],
        ]));
    }

    public function test_journal_currency_falls_back_to_base_for_multi_foreign(): void
    {
        $this->assertSame('LKR', $this->journalCurrency([
            ['currency' => 'USD'],
            ['currency' => 'EUR'],
        ]));
    }
}
