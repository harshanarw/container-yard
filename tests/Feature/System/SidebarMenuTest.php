<?php

namespace Tests\Feature\System;

use App\Models\Driver;
use Tests\Support\FeatureTestCase;

/**
 * The sidebar, after the Yard section was reorganised.
 *
 * A menu edit looks cosmetic and is not: each item carries its own permission
 * gate, and moving one between sections moves it under a *different* section
 * gate. Two ways that goes wrong, and both are silent —
 *
 *   a user who may use a screen stops seeing the section that holds it;
 *   a user who may not sees the link and gets a 403 on click.
 *
 * The Storage Calculator is gated `yard.view` and now lives under Billing,
 * whose own gate is a list of `billing.*` permissions. The Drivers master moved
 * to Setup and changed from a `users.role` check — the label column — to a real
 * permission, which is what the rest of the menu is drawn from.
 */
class SidebarMenuTest extends FeatureTestCase
{
    // ── Order and naming ────────────────────────────────────────────────────

    /**
     * The hire pair, in the order the trade happens and named for the direction.
     *
     * "Lessor On-Hire" and "Container Hires" are both hires, opposite ways
     * round, and side by side in a menu the old labels distinguished nothing.
     */
    public function test_the_yard_menu_names_the_hires_by_direction(): void
    {
        $this->actingAsSystemAdmin();
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('On-Hire In', $html);
        $this->assertStringContainsString('Rent Out', $html);
        $this->assertStringNotContainsString('Lessor On-Hire', $html);
        $this->assertStringNotContainsString('Container Hires', $html);
    }

    public function test_the_yard_menu_runs_in_lifecycle_order(): void
    {
        $this->actingAsSystemAdmin();
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $order = ['Gate In / Gate Out', 'Yard Overview', 'On-Hire In', 'Rent Out',
                  'Cargo Transfers', 'Reefer Plug Sessions', 'Yard Jobs'];

        $last = -1;
        foreach ($order as $label) {
            $at = strpos($html, '<span>' . $label . '</span>');
            $this->assertNotFalse($at, "{$label} is missing from the sidebar.");
            $this->assertGreaterThan($last, $at, "{$label} is out of order.");
            $last = $at;
        }
    }

    // ── The moved items ─────────────────────────────────────────────────────

    public function test_the_storage_calculator_moved_out_of_yard(): void
    {
        $this->actingAsSystemAdmin();
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $calculator = strpos($html, '<span>Storage Calculator</span>');
        $yardJobs   = strpos($html, '<span>Yard Jobs</span>');

        $this->assertNotFalse($calculator);
        $this->assertGreaterThan($yardJobs, $calculator, 'It belongs under Billing now.');
    }

    public function test_drivers_moved_to_setup(): void
    {
        $this->actingAsSystemAdmin();
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $drivers  = strpos($html, '<span>Drivers</span>');
        $jobTypes = strpos($html, '<span>Job Types</span>');

        $this->assertNotFalse($drivers);
        $this->assertGreaterThan($jobTypes, $drivers, 'Beside Job Types, under Gate Operations.');
    }

    // ── Nobody gained or lost access ────────────────────────────────────────

    /**
     * The calculator is gated `yard.view`, and the Billing section is not. A
     * gate officer holds the first and none of the second, so without widening
     * the section gate the move would have hidden a screen they may use.
     */
    public function test_a_gate_officer_can_still_reach_the_storage_calculator(): void
    {
        $this->actingAsRole('gate_officer');

        $this->assertStringContainsString(
            '<span>Storage Calculator</span>',
            $this->get(route('dashboard'))->assertOk()->getContent(),
        );

        $this->get(route('yard.storage'))->assertOk();
    }

    /** And is not offered the driver master, which they may not use. */
    public function test_a_gate_officer_is_not_offered_drivers(): void
    {
        $this->actingAsRole('gate_officer');

        $this->assertStringNotContainsString(
            '<span>Drivers</span>',
            $this->get(route('dashboard'))->assertOk()->getContent(),
        );
    }

    /**
     * The roles the old `users.role` list allowed must be exactly the roles the
     * permission allows, or the switch quietly changed who can edit a master.
     */
    public function test_a_yard_supervisor_still_manages_drivers(): void
    {
        $this->actingAsRole('yard_supervisor');

        Driver::create(['name' => 'D Perera', 'nic_number' => '901234567V']);

        $this->get(route('masters.drivers.index'))->assertOk();

        $this->assertStringContainsString(
            '<span>Drivers</span>',
            $this->get(route('dashboard'))->assertOk()->getContent(),
        );
    }
}
