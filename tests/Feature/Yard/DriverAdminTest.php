<?php

namespace Tests\Feature\Yard;

use App\Models\Driver;
use App\Models\GuardCapture;
use Tests\Support\FeatureTestCase;

/**
 * Driver master admin (Phase 2): list/search, view + history, edit, merge, delete,
 * gated to operations-management roles.
 */
class DriverAdminTest extends FeatureTestCase
{
    private function driver(array $attrs = []): Driver
    {
        return Driver::create(array_merge([
            'nic_number'     => 'AAA111',
            'name'           => 'Alpha',
            'phone'          => '+94770000001',
            'movement_count' => 1,
            'last_seen_at'   => now(),
        ], $attrs));
    }

    public function test_index_lists_and_searches_drivers(): void
    {
        $this->actingAsSystemAdmin();
        $this->driver(['nic_number' => 'AAA111', 'name' => 'Alpha Driver']);
        $this->driver(['nic_number' => 'BBB222', 'name' => 'Beta Driver', 'phone' => '+94779999999']);

        $this->get(route('masters.drivers.index'))
            ->assertOk()->assertSee('Alpha Driver')->assertSee('Beta Driver');

        $this->get(route('masters.drivers.index', ['q' => 'Beta']))
            ->assertOk()->assertSee('Beta Driver')->assertDontSee('Alpha Driver');
    }

    public function test_show_displays_driver_and_history(): void
    {
        $this->actingAsSystemAdmin();
        $driver = $this->driver(['nic_number' => 'AAA111']);
        GuardCapture::create([
            'reference_no'   => 'GP-HIST-1',
            'captured_at'    => now(),
            'direction'      => 'gate_in',
            'status'         => 'cleared',
            'nic_number'     => 'AAA111',
            'vehicle_number' => 'CAB-1',
        ]);

        $this->get(route('masters.drivers.show', $driver))
            ->assertOk()
            ->assertSee('AAA111')
            ->assertSee('GP-HIST-1');
    }

    public function test_update_edits_a_driver(): void
    {
        $this->actingAsSystemAdmin();
        $driver = $this->driver();

        $this->patch(route('masters.drivers.update', $driver), [
            'nic_number'     => 'AAA111',
            'name'           => 'Renamed Driver',
            'phone'          => '+94771112223',
            'license_number' => 'DL-9',
        ])->assertRedirect(route('masters.drivers.show', $driver));

        $this->assertDatabaseHas('drivers', [
            'id'             => $driver->id,
            'name'           => 'Renamed Driver',
            'phone'          => '+94771112223',
            'license_number' => 'DL-9',
        ]);
    }

    public function test_merge_consolidates_duplicate_and_repoints_history(): void
    {
        $this->actingAsSystemAdmin();
        $survivor = $this->driver(['nic_number' => 'AAA111', 'phone' => null, 'movement_count' => 2]);
        $dup      = $this->driver(['nic_number' => 'BBB222', 'phone' => '+94770000009', 'movement_count' => 3]);

        $capture = GuardCapture::create([
            'reference_no' => 'GP-M-1',
            'captured_at'  => now(),
            'direction'    => 'gate_in',
            'status'       => 'cleared',
            'nic_number'   => 'BBB222',
        ]);

        $this->post(route('masters.drivers.merge'), [
            'survivor_id'  => $survivor->id,
            'duplicate_id' => $dup->id,
        ])->assertRedirect(route('masters.drivers.show', $survivor));

        $this->assertModelMissing($dup);

        $survivor->refresh();
        $this->assertSame(5, $survivor->movement_count);       // 2 + 3
        $this->assertSame('+94770000009', $survivor->phone);    // blank backfilled from dup
        $this->assertSame('AAA111', $capture->refresh()->nic_number); // history repointed
    }

    public function test_destroy_removes_a_driver(): void
    {
        $this->actingAsSystemAdmin();
        $driver = $this->driver();

        $this->delete(route('masters.drivers.destroy', $driver))
            ->assertRedirect(route('masters.drivers.index'));

        $this->assertModelMissing($driver);
    }

    public function test_non_management_role_is_forbidden(): void
    {
        $this->actingAsRole('gate_officer');
        $this->driver();

        $this->get(route('masters.drivers.index'))->assertForbidden();
    }
}
