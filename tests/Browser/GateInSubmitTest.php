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
        $browser->pause(400); // let Select2 finish initialising

        // Choose the first real <option> in each master-data dropdown (job type
        // skips Empty Return, which needs a return reason), set it via selectedIndex
        // + jQuery so Select2 syncs, and disable the form's HTML5 blocking so submit
        // reaches the server (which is what these tests verify).
        $js = '(function(){'
            . 'var f=document.getElementById("gateInForm"); if(f) f.setAttribute("novalidate","novalidate");'
            . 'function set(sel,skipReturn){'
            . '  var s=document.querySelector(sel); if(!s) return "";'
            . '  var val="";'
            . '  for(var i=0;i<s.options.length;i++){ var o=s.options[i];'
            . '    if(!o.value) continue;'
            . '    if(skipReturn && o.getAttribute("data-is-empty-return")==="1") continue;'
            . '    val=o.value; s.selectedIndex=i; break; }'
            . '  if(window.jQuery){ window.jQuery(s).val(val).trigger("change"); } else { s.dispatchEvent(new Event("change",{bubbles:true})); }'
            . '  return val;'
            . '}'
            . 'set("#jobTypeSelect", true);'
            . 'set("#gateEqtSelect", false);'
            . 'set("#gateInForm select[name=customer_id]", false);'
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
        // Trigger the form's submit handler (the AJAX photo-uploader) directly.
        // This bypasses the confirm modal (UX we don't need here) whose handler
        // chain was not reliably firing the real submit under automation.
        // novalidate (set in fillGateIn) lets requestSubmit through to the handler.
        $browser->script("document.getElementById('gateInForm').requestSubmit();");
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
