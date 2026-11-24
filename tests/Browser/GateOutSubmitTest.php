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

            // For a laden container the lookup reveals the no-seal reason picker AND
            // opens the (otherwise collapsed) Export Information section it lives in.
            $browser->waitFor('#noSealWrapOut')
                ->assertVisible('#noSealWrapOut');
        });
    }

    /**
     * The seal comes over from the visit's gate-in (Phase 1, D9): pre-filled,
     * and a reason is asked for only when the clerk changes it.
     */
    public function test_seal_is_carried_from_the_gate_in_and_a_change_asks_for_a_reason(): void
    {
        $admin     = User::factory()->systemAdmin()->create();
        // The master says Empty with no seal; only the visit knows better.
        $container = $this->inYardContainer('SEAL7654321', ['cargo_status' => 'empty', 'seal_no' => null]);

        \App\Models\GateMovement::create([
            'container_id'   => $container->id,
            'container_no'   => $container->container_no,
            'customer_id'    => $container->customer_id,
            'movement_type'  => 'in',
            'size'           => $container->size,
            'container_type' => $container->type_code,
            'cargo_status'   => 'laden',
            'seal_no'        => 'SL-88001',
            'gate_in_time'   => now()->subDay(),
            'created_by'     => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $container) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#gateInCard');
            $this->openGateOutAndLookup($browser, $container->container_no);

            $browser->waitUntil("document.getElementById('sealNoOut').value === 'SL-88001'")
                ->assertVisible('#sealSourceOut')
                ->assertMissing('#sealChangeWrapOut');

            // A different seal reveals the reason field...
            $browser->clear('#sealNoOut')->type('#sealNoOut', 'SL-99002')
                ->waitFor('#sealChangeWrapOut')
                ->assertVisible('#sealChangeWrapOut');

            // ...and the same seal retyped in lower case hides it again.
            $browser->clear('#sealNoOut')->type('#sealNoOut', 'sl-88001')
                ->waitUntilMissing('#sealChangeWrapOut');
        });
    }
}
