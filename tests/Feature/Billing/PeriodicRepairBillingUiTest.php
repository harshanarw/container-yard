<?php

namespace Tests\Feature\Billing;

use Tests\Support\FeatureTestCase;

/**
 * Periodic repair billing — Phase 4 (UI). Smoke-covers the index and create
 * screens render, and that the module is permission-gated.
 */
class PeriodicRepairBillingUiTest extends FeatureTestCase
{
    public function test_index_renders_for_admin(): void
    {
        $this->actingAsSystemAdmin();
        $this->get(route('billing.repair.index'))
            ->assertOk()
            ->assertSee('Periodic Repair Billing');
    }

    public function test_create_screen_renders_for_admin(): void
    {
        $this->actingAsSystemAdmin();
        $this->get(route('billing.repair.create'))
            ->assertOk()
            ->assertSee('New Periodic Repair Bill')
            ->assertSee('Preview billable repairs');
    }

    public function test_module_is_permission_gated(): void
    {
        $this->actingAsRole('gate_officer');
        $this->get(route('billing.repair.index'))->assertForbidden();
    }
}
