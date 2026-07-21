<?php

namespace Tests\Browser;

use App\Models\Container;
use App\Models\User;
use Laravel\Dusk\Browser;

/**
 * Gate-In end-to-end + the AJAX error surfacing (422 → toast).
 *
 * The three master-data selects (job type, equipment, customer) are Select2, which
 * Dusk can't drive directly — and picking ids from the DB is fragile because the
 * dropdowns may filter/omit them. Instead we choose the first real <option> in
 * each select in the browser. Submit goes through the confirmation modal, driven
 * via JS to avoid click-interception on the long form.
 */
class GateInSubmitTest extends BrowserTestCase
{
    private function fillGateIn(Browser $browser, string $containerNo, ?string $vehicle): void
    {
        // Choose the first selectable option in each master-data dropdown, skipping
        // an Empty-Return job type (which requires a return reason before submit).
        $js = '(function(){'
            . 'function trig(s){ if(window.jQuery) jQuery(s).trigger("change"); else s.dispatchEvent(new Event("change")); }'
            . 'function pick(sel){ var s=document.querySelector(sel); if(!s) return; for(var i=0;i<s.options.length;i++){ if(s.options[i].value){ s.value=s.options[i].value; break; } } trig(s); }'
            . 'var js=document.querySelector("#jobTypeSelect");'
            . 'if(js){ for(var i=0;i<js.options.length;i++){ var o=js.options[i]; if(o.value && o.getAttribute("data-is-empty-return")!=="1"){ js.value=o.value; break; } } trig(js); }'
            . 'pick("#gateEqtSelect");'
            . 'pick("#gateInForm select[name=customer_id]");'
            . '})();';
        $browser->script($js);

        $browser->type('#containerNoIn', $containerNo)
            ->select('condition', 'sound');

        if ($vehicle !== null) {
            $browser->type('#vehiclePlateIn', $vehicle);
        }
    }

    private function submitGateIn(Browser $browser): void
    {
        // JS clicks avoid "element click intercepted" (button far down / inside a
        // modal); the handlers listen for 'click', so this drives them correctly.
        $browser->script("document.getElementById('btnSubmitGateIn').click();");
        $browser->waitFor('#confirmGateInModal')->pause(700);   // Bootstrap fade-in
        $browser->script("document.getElementById('confirmGateInBtn').click();");
    }

    public function test_happy_path_records_the_container(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#containerNoIn');
            $this->fillGateIn($browser, 'DUSK1234567', 'TRUCK01');
            $this->submitGateIn($browser);
            $browser->pause(4000);       // let the AJAX submit complete
        });

        $this->assertDatabaseHas('containers', [
            'container_no' => 'DUSK1234567',
            'status'       => 'in_yard',
        ]);
    }

    public function test_blank_vehicle_shows_an_error(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#containerNoIn');
            $this->fillGateIn($browser, 'NOVE1234567', null);   // no vehicle plate
            $this->submitGateIn($browser);
            $browser->waitFor('.toast-body', 12)
                ->assertSeeIn('#toastContainer', 'required');    // "... field is required."
        });

        $this->assertDatabaseMissing('containers', [
            'container_no' => 'NOVE1234567',
            'status'       => 'in_yard',
        ]);
    }

    public function test_duplicate_container_shows_an_error(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        Container::factory()->create(['container_no' => 'DUPL1234567', 'status' => 'in_yard']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#containerNoIn');
            $this->fillGateIn($browser, 'DUPL1234567', 'TRUCK01');
            $this->submitGateIn($browser);
            $browser->waitFor('.toast-body', 12)
                ->assertSeeIn('#toastContainer', 'already in the yard');
        });
    }
}
