<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;

/**
 * [JS] The Gate In / Gate Out toggle shows the right card and "Recording:" bar.
 */
class GateTabToggleTest extends BrowserTestCase
{
    public function test_toggle_between_gate_in_and_gate_out_cards(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/yard/gate')
                ->waitFor('#gateInCard')
                // Gate In is the default; Gate Out card is hidden (d-none).
                ->assertVisible('#gateInCard')
                ->assertMissing('#gateOutCard')
                // Switch to Gate Out.
                ->click('#btnGateOut')
                ->waitFor('#gateOutCard')
                ->assertVisible('#gateOutCard')
                ->assertMissing('#gateInCard')
                ->assertSee('GATE OUT')
                // Back to Gate In.
                ->click('#btnGateIn')
                ->waitFor('#gateInCard')
                ->assertVisible('#gateInCard')
                ->assertMissing('#gateOutCard');
        });
    }
}
