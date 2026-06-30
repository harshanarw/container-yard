<?php

namespace Tests\Unit\Finance;

use App\Models\CompanySetting;
use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Currency conversion math + the foreign-rate validation guards.
 *
 * This project's migrations contain MySQL-only DDL, so they can't run on the
 * SQLite test DB. The two small tables these tests need are provisioned directly
 * here instead of using RefreshDatabase — keeping the unit tests fast and
 * database-engine independent. (Full feature tests that need the whole schema
 * should run against a MySQL test database.)
 *
 * Convention under test: exchange_rate = foreign → base (1 USD = 300 LKR).
 */
class CurrencyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('company_settings');
        Schema::create('company_settings', function (Blueprint $t) {
            $t->id();
            $t->string('company_name')->nullable();
            $t->string('default_currency_code', 10)->nullable();
            $t->timestamps();
        });

        Schema::dropIfExists('exchange_rates');
        Schema::create('exchange_rates', function (Blueprint $t) {
            $t->id();
            $t->date('rate_date');
            $t->string('from_currency_code', 10);
            $t->string('to_currency_code', 10);
            $t->decimal('rate', 18, 6);
            $t->string('notes')->nullable();
            $t->timestamps();
        });

        CompanySetting::create(['company_name' => 'Test Co', 'default_currency_code' => 'LKR']);
        Cache::forget('company_settings');
    }

    public function test_tariff_multiplier_is_one_for_base_and_rate_for_foreign(): void
    {
        $this->assertSame(1.0, CurrencyService::tariffMultiplier('LKR', 300));
        $this->assertSame(300.0, CurrencyService::tariffMultiplier('USD', 300));
        $this->assertSame(300.0, CurrencyService::tariffMultiplier('usd', 300)); // case-insensitive
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
