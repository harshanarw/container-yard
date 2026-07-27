<?php

namespace Tests\Browser;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\CurrencyService;
use Laravel\Dusk\Browser;

/**
 * Browser cover for the periodic repair-billing currency / exchange-rate
 * behaviour — a client-side interaction (Select2 change → AJAX to the FX master)
 * that PHPUnit can't exercise. Base currency locks the rate to 1; a foreign
 * currency auto-loads the configured rate.
 */
class RepairBillingCurrencyRateTest extends BrowserTestCase
{
    public function test_exchange_rate_autoloads_when_the_currency_changes(): void
    {
        $admin   = User::factory()->systemAdmin()->create();
        $base    = strtoupper((string) CurrencyService::defaultCurrency());
        $foreign = $base === 'USD' ? 'EUR' : 'USD';
        $rate    = 305.5;

        // Make the foreign currency selectable and give it a known rate for today.
        Currency::updateOrCreate(
            ['code' => $foreign],
            ['name' => $foreign . ' Currency', 'is_active' => true, 'sort_order' => 90]
        );
        ExchangeRate::updateOrCreate(
            ['from_currency_code' => $foreign, 'to_currency_code' => $base, 'rate_date' => now()->toDateString()],
            ['rate' => $rate]
        );

        $this->browse(function (Browser $browser) use ($admin, $foreign, $rate) {
            $browser->loginAs($admin)
                ->visit('/billing/repair/create')
                ->waitFor('#invoice_currency')
                ->pause(800); // let Select2 init + the initial loadRate() run

            // Base currency is selected by default → rate is locked at 1.
            $this->assertSame('1', $browser->value('#exchange_rate'));

            // Switch to the foreign currency → the rate auto-loads from the master.
            $browser->script('window.jQuery("#invoice_currency").val("' . $foreign . '").trigger("change");');
            $browser->pause(1800); // allow the fx-rate fetch to resolve

            $this->assertEqualsWithDelta(
                $rate,
                (float) $browser->value('#exchange_rate'),
                0.001,
                'Exchange rate should auto-load for the selected foreign currency.'
            );
        });
    }
}
