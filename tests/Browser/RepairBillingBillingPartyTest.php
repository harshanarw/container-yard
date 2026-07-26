<?php

namespace Tests\Browser;

use App\Models\Customer;
use App\Models\User;
use Laravel\Dusk\Browser;

/**
 * Browser cover for the periodic repair-billing "billing party auto-fills from
 * the selected customer" behaviour — a client-side (Select2 + jQuery) interaction
 * that PHPUnit feature tests can't exercise because they don't run JavaScript.
 *
 * Selecting a customer should set the Billing Party to that customer's configured
 * billing party, or to the customer itself when none is configured.
 */
class RepairBillingBillingPartyTest extends BrowserTestCase
{
    public function test_billing_party_autofills_from_the_selected_customer(): void
    {
        $admin  = User::factory()->systemAdmin()->create();
        $parent = Customer::factory()->create();                                   // a parent billing entity
        $child  = Customer::factory()->create(['billing_party_id' => $parent->id]); // bills to the parent
        $solo   = Customer::factory()->create(['billing_party_id' => null]);        // no explicit billing party

        $this->browse(function (Browser $browser) use ($admin, $parent, $child, $solo) {
            $browser->loginAs($admin)
                ->visit(route('billing.repair.create'))
                ->waitFor('#customer_id')
                ->pause(500); // let the global Select2 init run

            // Selecting the child customer → billing party becomes its parent.
            $browser->script('window.jQuery("#customer_id").val(' . $child->id . ').trigger("change");');
            $browser->pause(400);
            $this->assertSame(
                (string) $parent->id,
                $browser->value('#billing_party_id'),
                'Billing party should auto-fill with the customer\'s configured billing party.'
            );

            // Selecting a customer with no billing party → falls back to itself.
            $browser->script('window.jQuery("#customer_id").val(' . $solo->id . ').trigger("change");');
            $browser->pause(400);
            $this->assertSame(
                (string) $solo->id,
                $browser->value('#billing_party_id'),
                'Billing party should fall back to the customer itself when none is configured.'
            );
        });
    }
}
