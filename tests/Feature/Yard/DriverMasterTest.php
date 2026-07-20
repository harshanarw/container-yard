<?php

namespace Tests\Feature\Yard;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\EquipmentType;
use App\Models\YardJobType;
use App\Services\DriverService;
use Tests\Support\FeatureTestCase;

/**
 * Driver master (Phase 1): auto-populated from gate movements / guard captures,
 * keyed on NIC, with a name/NIC/phone typeahead endpoint.
 */
class DriverMasterTest extends FeatureTestCase
{
    private function service(): DriverService
    {
        return app(DriverService::class);
    }

    public function test_remember_creates_a_driver_keyed_on_nic(): void
    {
        $driver = $this->service()->remember('Sunil Perera', '892345678V', '+94771234567');

        $this->assertNotNull($driver);
        $this->assertDatabaseHas('drivers', [
            'nic_number'     => '892345678V',
            'name'           => 'Sunil Perera',
            'phone'          => '+94771234567',
            'movement_count' => 1,
        ]);
    }

    public function test_remember_overwrites_phone_and_bumps_count_on_repeat(): void
    {
        $this->service()->remember('Sunil Perera', '892345678V', '+94770000000');
        // Lower-case NIC with surrounding space → same normalised key.
        $this->service()->remember('Sunil P.', '  892345678v ', '+94779999999');

        $this->assertDatabaseCount('drivers', 1);
        $this->assertDatabaseHas('drivers', [
            'nic_number'     => '892345678V',
            'name'           => 'Sunil P.',
            'phone'          => '+94779999999',
            'movement_count' => 2,
        ]);
    }

    public function test_remember_skips_when_nic_is_blank(): void
    {
        $this->assertNull($this->service()->remember('No NIC Driver', '', '+94770000000'));
        $this->assertNull($this->service()->remember('Also None', null, '123'));

        $this->assertDatabaseCount('drivers', 0);
    }

    public function test_remember_does_not_wipe_existing_details_with_blanks(): void
    {
        $this->service()->remember('Sunil Perera', '892345678V', '+94771234567');
        $this->service()->remember('', '892345678V', ''); // blank name/phone must not overwrite

        $this->assertDatabaseHas('drivers', [
            'nic_number' => '892345678V',
            'name'       => 'Sunil Perera',
            'phone'      => '+94771234567',
        ]);
    }

    public function test_driver_search_matches_name_nic_and_phone(): void
    {
        $this->actingAsSystemAdmin();
        Driver::create([
            'nic_number'   => '892345678V',
            'name'         => 'Sunil Perera',
            'phone'        => '+94771234567',
            'last_seen_at' => now(),
        ]);

        foreach (['Sunil', '892345', '771234'] as $q) {
            $this->getJson(route('yard.driver-search', ['q' => $q]))
                ->assertOk()
                ->assertJsonFragment(['nic_number' => '892345678V']);
        }
    }

    public function test_driver_search_ignores_too_short_query(): void
    {
        $this->actingAsSystemAdmin();
        Driver::create(['nic_number' => '892345678V', 'name' => 'Sunil', 'last_seen_at' => now()]);

        $this->getJson(route('yard.driver-search', ['q' => 'S']))
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_gate_in_populates_the_driver_master(): void
    {
        $this->actingAsSystemAdmin();

        $customer = Customer::factory()->create();
        $eqt      = EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();
        $jobType  = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => 'DRVR1234567',
            'equipment_type_id' => $eqt->id,
            'customer_id'       => $customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'driver_name'       => 'Kamal Silva',
            'driver_ic'         => '901234567V',
            'driver_phone'      => '+94712223334',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('drivers', [
            'nic_number' => '901234567V',
            'name'       => 'Kamal Silva',
            'phone'      => '+94712223334',
        ]);
    }
}
