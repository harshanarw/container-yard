<?php

namespace Tests\Browser;

use App\Models\CompanySetting;
use App\Models\Container;
use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/**
 * Gate-Out: the container lookup panel, the not-releasable block, and the
 * seal-reason reveal for a laden container.
 *
 * The container picker is an AJAX Select2 whose lookup only fires on real
 * selection, so we inject the option and trigger the lookup via the (hidden)
 * search button — the same hook the app uses to auto-lookup.
 */
class GateOutSubmitTest extends BrowserTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable the seal policy for the whole run, BEFORE any page load — the
        // long-running server caches settings, so flipping it mid-run wouldn't be
        // picked up. Empty / under-repair containers are unaffected by the policy,
        // so the other two tests still pass.
        DB::table('company_settings')->update(['require_seal_for_laden' => true]);
        CompanySetting::flushCache();
    }

    private function inYardContainer(string $no, array $attrs = []): Container
    {
        return Container::factory()->create(array_merge([
            'container_no'      => $no,
            'status'            => 'in_yard',
            'equipment_type_id' => EquipmentType::query()->firstOrFail()->id,
        ], $attrs));
    }

    /** Switch to the Gate-Out card, choose a container by number, and run the lookup. */
    private function openGateOutAndLookup(Browser $browser, string $no): void
    {
        $browser->script("document.getElementById('btnGateOut').click();");
        $browser->waitFor('#containerSearch');

        $js = '(function(){'
            . 'var s=document.getElementById("containerSearch");'
            . 'if(s){ var has=false; for(var i=0;i<s.options.length;i++){ if(s.options[i].value=="' . $no . '"){has=true;break;} }'
            . '  if(!has){ var o=document.createElement("option"); o.value="' . $no . '"; o.text="' . $no . '"; s.appendChild(o); }'
            . '  s.value="' . $no . '"; if(window.jQuery){ window.jQuery(s).val("' . $no . '").trigger("change"); } }'
            . 'var b=document.getElementById("containerSearchBtn"); if(b) b.click();'   // fires doLookup()
            . '})();';
        $browser->script($js);

        $browser->waitFor('#containerInfoBox');
    }

    public function test_releasable_container_gates_out(): void
    {
        $admin     = User::factory()->systemAdmin()->create();
        $container = $this->inYardContainer('OUTB1234567');

        $this->browse(function (Browser $browser) use ($admin, $container) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#gateInCard');
            $this->openGateOutAndLookup($browser, $container->container_no);

            $browser->type('#vehiclePlateOut', 'TRUCK09')
                ->type('#driverNameOut', 'Nimal Perera')
                ->type('#driverIcOut', '901234567V');

            // lookupDone is true for a releasable container, so requestSubmit fires
            // the AJAX submit (novalidate skips HTML5 blocking).
            $browser->script("var f=document.getElementById('gateOutForm'); f.setAttribute('novalidate','novalidate'); f.requestSubmit();");
            $browser->pause(4000);
        });

        $this->assertDatabaseHas('gate_movements', [
            'container_id'  => $container->id,
            'movement_type' => 'out',
        ]);
    }

    public function test_under_repair_container_is_flagged_not_releasable(): void
    {
        $admin     = User::factory()->systemAdmin()->create();
        $container = $this->inYardContainer('REPR1234567', ['status' => 'in_repair', 'condition' => 'require_repair']);

        $this->browse(function (Browser $browser) use ($admin, $container) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#gateInCard');
            $this->openGateOutAndLookup($browser, $container->container_no);

            $browser->assertSeeIn('#containerInfoBox', 'Cannot be gated out');
        });

        $this->assertDatabaseMissing('gate_movements', [
            'container_id'  => $container->id,
            'movement_type' => 'out',
        ]);
    }

    public function test_no_seal_reason_reveals_for_a_laden_container(): void
    {
        // Policy enabled in setUp (before any page load) so the server renders the
        // reason field for a laden container.
        $admin     = User::factory()->systemAdmin()->create();
        $container = $this->inYardContainer('LADN1234567', ['cargo_status' => 'laden']);

        $this->browse(function (Browser $browser) use ($admin, $container) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#gateInCard');
            $this->openGateOutAndLookup($browser, $container->container_no);

            // DIAGNOSTIC: capture the DOM state so the failure message tells us
            // whether the wrap is absent (policy off) or present-but-hidden (reveal
            // didn't fire), plus what the lookup panel actually rendered.
            $diag = $browser->script(
                'var w=document.getElementById("noSealWrapOut");' .
                'var info=document.getElementById("containerInfoBox");' .
                'return JSON.stringify({wrapPresent:!!w, wrapClass:w?w.className:null, ' .
                'info:info?info.innerText.replace(/\s+/g," ").slice(0,160):null});'
            )[0];

            // Force the full state (incl. the Cargo the panel shows) into the
            // message: this fails while the wrap is hidden so we can read `info`.
            $this->assertStringNotContainsString('d-none', (string) $diag, 'Wrap present but hidden — reveal did not fire → ' . $diag);

            $browser->waitFor('#noSealWrapOut')
                ->assertVisible('#noSealWrapOut');
        });
    }
}
