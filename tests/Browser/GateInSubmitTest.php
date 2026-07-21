<?php

namespace Tests\Browser;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\User;
use App\Models\YardJobType;
use Laravel\Dusk\Browser;

/**
 * Gate-In end-to-end + the AJAX error surfacing (422 → toast).
 *
 * The master-data fields are Select2, which Dusk can't drive and whose values
 * don't reliably stick when set by option text. We set them by real DB id and
 * inject the <option> if the dropdown doesn't list it — the server only needs the
 * ids to exist — then submit in the same step so nothing resets them first.
 */
class GateInSubmitTest extends BrowserTestCase
{
    /** Real ids the server will accept for the three required selects. */
    private function masterIds(): array
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->firstOrFail();

        return [
            'job'  => (string) $jobType->id,
            'eqt'  => (string) EquipmentType::query()->firstOrFail()->id,
            'cust' => (string) Customer::factory()->create()->id,
        ];
    }

    private function fillAndSubmit(Browser $browser, array $ids, string $containerNo, ?string $vehicle): void
    {
        $browser->type('#containerNoIn', $containerNo)
            ->select('condition', 'sound');

        if ($vehicle !== null) {
            $browser->type('#vehiclePlateIn', $vehicle);
        }

        // Set each Select2 by real id (add the option if missing), turn off the
        // form's HTML5 blocking, and submit — all together so the values can't be
        // reset before the AJAX submit fires.
        $js = '(function(){'
            . 'function setById(sel,id){ var s=document.querySelector(sel); if(!s||!id) return;'
            . '  var has=false; for(var i=0;i<s.options.length;i++){ if(s.options[i].value==id){ has=true; break; } }'
            . '  if(!has){ var o=document.createElement("option"); o.value=id; o.text=id; s.appendChild(o); }'
            . '  s.value=id; if(window.jQuery){ window.jQuery(s).val(id).trigger("change"); } else { s.dispatchEvent(new Event("change",{bubbles:true})); } }'
            . 'setById("#jobTypeSelect","' . $ids['job'] . '");'
            . 'setById("#gateEqtSelect","' . $ids['eqt'] . '");'
            . 'setById("#gateInForm select[name=customer_id]","' . $ids['cust'] . '");'
            . 'var f=document.getElementById("gateInForm"); f.setAttribute("novalidate","novalidate"); f.requestSubmit();'
            . '})();';
        $browser->script($js);
    }

    public function test_happy_path_records_the_container(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $ids   = $this->masterIds();

        $this->browse(function (Browser $browser) use ($admin, $ids) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#containerNoIn');
            $this->fillAndSubmit($browser, $ids, 'DUSK1234567', 'TRUCK01');
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
        $ids   = $this->masterIds();

        $this->browse(function (Browser $browser) use ($admin, $ids) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#containerNoIn');
            $this->fillAndSubmit($browser, $ids, 'NOVE1234567', null);   // no vehicle plate
            $browser->waitFor('.toast-body', 12)
                ->assertSeeIn('#toastContainer', 'vehicle');             // vehicle-specific error
        });

        $this->assertDatabaseMissing('containers', [
            'container_no' => 'NOVE1234567',
            'status'       => 'in_yard',
        ]);
    }

    public function test_duplicate_container_shows_an_error(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $ids   = $this->masterIds();
        Container::factory()->create(['container_no' => 'DUPL1234567', 'status' => 'in_yard']);

        $this->browse(function (Browser $browser) use ($admin, $ids) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#containerNoIn');
            $this->fillAndSubmit($browser, $ids, 'DUPL1234567', 'TRUCK01');
            $browser->waitFor('.toast-body', 12)
                ->assertSeeIn('#toastContainer', 'already in the yard');
        });
    }
}
