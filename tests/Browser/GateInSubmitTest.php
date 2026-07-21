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
 * The three master-data selects (job type, equipment, customer) are Select2, which
 * Dusk can't drive directly, so we set them via jQuery. Submit goes through the
 * confirmation modal, then the form posts via fetch.
 */
class GateInSubmitTest extends BrowserTestCase
{
    /** Valid ids for the seeded master data + a fresh customer. */
    private function masterIds(): array
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->firstOrFail();

        return [
            'job_type_id'       => $jobType->id,
            'equipment_type_id' => EquipmentType::query()->firstOrFail()->id,
            'customer_id'       => Customer::factory()->create()->id,
        ];
    }

    private function fillGateIn(Browser $browser, array $ids, string $containerNo, ?string $vehicle): void
    {
        // Select2 fields — set through jQuery and fire change so the app's handlers run.
        $browser->script([
            "\$('#jobTypeSelect').val('{$ids['job_type_id']}').trigger('change');",
            "\$('#gateEqtSelect').val('{$ids['equipment_type_id']}').trigger('change');",
            "\$('#gateInForm select[name=customer_id]').val('{$ids['customer_id']}').trigger('change');",
        ]);

        $browser->type('#containerNoIn', $containerNo)
            ->select('condition', 'sound');

        if ($vehicle !== null) {
            $browser->type('#vehiclePlateIn', $vehicle);
        }
    }

    private function submitGateIn(Browser $browser): void
    {
        $browser->click('#btnSubmitGateIn')
            ->waitFor('#confirmGateInModal')
            ->pause(700)                 // Bootstrap modal fade-in
            ->click('#confirmGateInBtn');
    }

    public function test_happy_path_records_the_container(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $ids   = $this->masterIds();

        $this->browse(function (Browser $browser) use ($admin, $ids) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#containerNoIn');
            $this->fillGateIn($browser, $ids, 'DUSK1234567', 'TRUCK01');
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
        $ids   = $this->masterIds();

        $this->browse(function (Browser $browser) use ($admin, $ids) {
            $browser->loginAs($admin)->visit('/yard/gate')->waitFor('#containerNoIn');
            $this->fillGateIn($browser, $ids, 'NOVE1234567', null);   // no vehicle plate
            $this->submitGateIn($browser);
            $browser->waitFor('.toast-body')
                ->assertSeeIn('#toastContainer', 'required');          // "... field is required."
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
            $this->fillGateIn($browser, $ids, 'DUPL1234567', 'TRUCK01');
            $this->submitGateIn($browser);
            $browser->waitFor('.toast-body')
                ->assertSeeIn('#toastContainer', 'already in the yard');
        });
    }
}
