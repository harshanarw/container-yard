<?php

namespace Tests\Browser;

use App\Models\Driver;
use App\Models\User;
use Laravel\Dusk\Browser;

/**
 * [JS] Typing in the Driver Name field surfaces a matching master driver in a
 * dropdown; picking it fills name, NIC and phone.
 */
class DriverTypeaheadTest extends BrowserTestCase
{
    public function test_typeahead_fills_all_three_driver_fields(): void
    {
        Driver::create([
            'nic_number'   => '901234567V',
            'name'         => 'Kamal Silva',
            'phone'        => '+94712223334',
            'last_seen_at' => now(),
        ]);

        $admin = User::factory()->systemAdmin()->create();

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/yard/gate')
                ->waitFor('#driverNameIn')
                ->type('#driverNameIn', 'Kamal')     // fires the debounced lookup
                ->waitFor('.driver-ac-item')          // dropdown appears
                ->click('.driver-ac-item')            // pick the match
                ->waitUntilMissing('.driver-ac-item')
                ->assertInputValue('#driverNameIn', 'Kamal Silva')
                ->assertInputValue('#driverIcIn', '901234567V')
                ->assertInputValue('#driverPhoneIn', '+94712223334');
        });
    }
}
