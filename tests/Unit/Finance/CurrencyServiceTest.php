<?php

namespace Tests\Unit\Finance;

use App\Models\CompanySetting;
use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Currency conversion math + the foreign-rate validation guards.
 *
 * Base currency is forced to LKR via CompanySetting; the convention under test
 * is exchange_rate = foreign → base (1 USD = 300 LKR).
 */
class CurrencyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CompanySetting::query()->delete();
        CompanySetting::create(['company_name' => 'Test Co', 'default_currency_code' => 'LKR']);
        Cache::forget('company_settings');
    }

    public function test_tariff_multiplier_is_one_for_base_and_rate_for_foreign(): void
    {
        $this->assertSame(1.0, CurrencyService::tariffMultiplier('LKR', 300));
        $this->assertSame(300.0, CurrencyService::tariffMultiplier('USD', 300));
        // case-insensitive
        $this->assertSame(300.0, CurrencyService::tariffMultiplier('usd', 300));
    }

    public function test_invoice_display_factor_is_one_for_base_and_inverse_for_foreign(): void
    {
        $this->assertSame(1.0, CurrencyService::invoiceDisplayFactor('LKR', 300));
        $this->assertEqualsWithDelta(1 / 300, CurrencyService::invoiceDisplayFactor('USD', 300), 1e-9);
    }

    public function test_require_rate_returns_one_for_base_currency(): void
    {
        $this->assertSame(1.0, CurrencyService::requireRate('LKR', null));
        $this->assertSame(1.0, CurrencyService::requireRate('LKR', 999));
    }

    public function test_require_rate_returns_positive_rate_for_foreign(): void
    {
        $this->assertSame(300.0, CurrencyService::requireRate('USD', 300));
    }

    public function test_require_rate_throws_when_foreign_rate_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CurrencyService::requireRate('USD', null);
    }

    public function test_require_rate_throws_when_foreign_rate_is_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CurrencyService::requireRate('USD', 0);
    }

    public function test_resolve_rate_or_fail_returns_one_for_base(): void
    {
        $this->assertSame(1.0, CurrencyService::resolveRateOrFail('LKR'));
    }

    public function test_resolve_rate_or_fail_returns_configured_rate(): void
    {
        ExchangeRate::create([
            'rate_date'          => now()->toDateString(),
            'from_currency_code' => 'USD',
            'to_currency_code'   => 'LKR',
            'rate'               => 305.5,
        ]);

        $this->assertSame(305.5, CurrencyService::resolveRateOrFail('USD'));
    }

    public function test_resolve_rate_or_fail_throws_when_no_rate_configured(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CurrencyService::resolveRateOrFail('USD');
    }
}
