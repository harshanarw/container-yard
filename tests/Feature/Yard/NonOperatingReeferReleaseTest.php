<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Services\ContainerMrStatusService;
use App\Support\MrStatusCatalogue as Cat;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * What a NOR is *not* asked for (Phase 3): a PTI at the gate, and a place on the
 * M&R board.
 *
 * A pre-trip inspection tests refrigeration that is about to be used. A NOR is
 * leaving as dry cargo, so demanding one asks the yard to service equipment
 * nobody will run — and a board reading "PTI due" against every NOR is a board
 * people stop opening.
 *
 * The distinction this file exists to hold: the release is judged on **how the
 * box is leaving**, not how it arrived. A container that came in as a NOR may be
 * going out loaded with reefer cargo and genuinely needs a PTI.
 */
class NonOperatingReeferReleaseTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-07 09:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The M&R board ───────────────────────────────────────────────────────

    /** An operating reefer with a stale PTI still reads "PTI due". Unchanged. */
    public function test_an_operating_reefer_without_a_pti_still_shows_pti_due(): void
    {
        $status = $this->resolveFor('operating');

        $this->assertContains($status->code, [Cat::PTI_DUE, Cat::PTI_FAILED]);
    }

    /** A NOR does not. Nobody is going to PTI a box leaving as dry cargo. */
    public function test_a_nor_does_not_show_pti_due(): void
    {
        $status = $this->resolveFor('non_operating');

        $this->assertNotContains($status->code, [Cat::PTI_DUE, Cat::PTI_FAILED]);
    }

    /** Nor does it carry the expired-PTI chip on whatever status it does have. */
    public function test_a_nor_carries_no_expired_pti_chip(): void
    {
        $status = $this->resolveFor('non_operating');

        $this->assertNotContains(Cat::MODIFIER_PTI_EXPIRED, $status->modifiers);
    }

    /**
     * A reefer recorded before the column existed reads as operating, so the
     * board it has always shown is the board it keeps showing.
     */
    public function test_a_reefer_with_no_recorded_mode_still_shows_pti_due(): void
    {
        $status = $this->resolveFor(null);

        $this->assertContains($status->code, [Cat::PTI_DUE, Cat::PTI_FAILED]);
    }

    /**
     * Suppressing the rung must not touch the container's PTI record. The
     * moment the box comes back as an operating reefer the status has to
     * reappear without anyone re-entering anything.
     */
    public function test_the_containers_pti_record_is_untouched_by_being_a_nor(): void
    {
        $container = $this->reeferInYard('non_operating');

        $this->assertFalse($container->fresh()->hasValidPti(), 'The PTI is still stale - only the rung is quiet.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** A reefer in the yard with no valid PTI, gated in under the given mode. */
    private function reeferInYard(?string $mode): Container
    {
        $type = \App\Models\EquipmentType::all()->first(fn ($e) => $e->isReefer())
            ?? $this->fail('No reefer equipment type is seeded.');

        $container = Container::factory()->create([
            'customer_id'       => $this->customer->id,
            'equipment_type_id' => $type->id,
            'type_code'         => $type->type_code,
            'cargo_status'      => 'laden',
            'status'            => 'in_yard',
            // 'none', not null — the column is a non-null enum, and "never
            // inspected" is what it means. `hasValidPti()` reads anything but
            // 'passed' as no valid PTI, which is the state under test.
            'pti_status'        => 'none',
            'pti_at'            => null,
        ]);

        GateMovement::create([
            'container_id'   => $container->id,
            'container_no'   => $container->container_no,
            'customer_id'    => $this->customer->id,
            'movement_type'  => 'in',
            'size'           => $type->size,
            'container_type' => $type->type_code,
            'cargo_status'   => 'laden',
            'reefer_mode'    => $mode,
            'gate_in_time'   => now()->subDays(3),
            'created_by'     => auth()->id(),
        ]);

        return $container;
    }

    private function resolveFor(?string $mode)
    {
        $container = $this->reeferInYard($mode);
        $service   = app(ContainerMrStatusService::class);

        return $service->resolve($service->contextForContainer($container->fresh()));
    }
}
