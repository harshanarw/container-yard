<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\ContainerHold;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\Estimate;
use App\Models\ReeferPtiInspection;
use App\Models\WorkOrder;
use App\Models\YardJobType;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Export readiness as a *query*, which is the part that replaces a nightly job.
 *
 * Almost everything that changes a container's readiness changes a row, so an
 * observer catches it and the stored flag stays true. One thing does not: a
 * reefer's PTI lapses because a date passed. Rather than recompute the world
 * every night — which leaves the answer wrong for up to a day — the resolver
 * stores the date the verdict stops being true, and Container::scopeExportReady
 * compares it against today.
 *
 * The test that matters is the last one: a reefer stored as ready, whose PTI
 * date has since passed, must not be returned. No job runs in between.
 */
class ExportReadinessTest extends FeatureTestCase
{
    private static int $woSeq = 0;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-01 12:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function equipment(bool $reefer): EquipmentType
    {
        $match = EquipmentType::all()->first(fn ($e) => $e->isReefer() === $reefer);

        if (! $match) {
            $this->markTestSkipped($reefer ? 'No reefer equipment type seeded.' : 'No dry equipment type seeded.');
        }

        return $match;
    }

    private function gateIn(string $containerNo, bool $reefer = false): Container
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => $containerNo,
            'equipment_type_id' => $this->equipment($reefer)->id,
            'customer_id'       => $this->customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => '2026-08-01 08:00:00',
        ])->assertSessionHasNoErrors();

        return Container::where('container_no', $containerNo)->firstOrFail();
    }

    /** Take a container through a repair that passes QC, so it is genuinely ready. */
    private function makeReady(Container $container): void
    {
        WorkOrder::create([
            'wo_no'        => 'WO-XR' . str_pad((string) ++self::$woSeq, 5, '0', STR_PAD_LEFT),
            'estimate_id'  => Estimate::query()->firstOrFail()->id,
            'container_id' => $container->id,
            'container_no' => $container->container_no,
            'customer_id'  => $this->customer->id,
            'status'       => 'pending',
        ])->update(['status' => 'closed', 'qc_at' => now()]);
    }

    private function isReturnedByScope(Container $container): bool
    {
        return Container::exportReady()->whereKey($container->id)->exists();
    }

    public function test_a_repaired_dry_container_is_returned(): void
    {
        $container = $this->gateIn('XRDY0000001');
        $this->makeReady($container);

        $this->assertTrue((bool) $container->refresh()->export_ready);
        $this->assertNull($container->mr_status_expires_at,
            'Nothing about a dry container can go stale on its own.');
        $this->assertTrue($this->isReturnedByScope($container));
    }

    public function test_a_held_container_is_not_returned(): void
    {
        $container = $this->gateIn('XRDY0000002');
        $this->makeReady($container);

        ContainerHold::create([
            'container_id' => $container->id,
            'hold_type'    => 'stop_release',
            'placed_at'    => now(),
        ]);

        $this->assertFalse($this->isReturnedByScope($container->refresh()));
    }

    public function test_a_reefer_with_a_live_pti_is_returned_and_carries_its_boundary(): void
    {
        $container = $this->gateIn('XRRF0000001', reefer: true);

        ReeferPtiInspection::create([
            'container_id' => $container->id,
            'inspected_at' => now(),
            'result'       => 'pass',
            'valid_until'  => Carbon::today()->addDays(30),
        ]);
        $container->forceFill(['pti_status' => 'passed', 'pti_at' => now()])->save();

        $this->makeReady($container);
        $container->refresh();

        $this->assertTrue((bool) $container->export_ready);
        $this->assertSame(
            Carbon::today()->addDays(30)->toDateString(),
            $container->mr_status_expires_at?->toDateString(),
            'A reefer stores the date its readiness lapses.'
        );
        $this->assertTrue($this->isReturnedByScope($container));
    }

    /**
     * The case the whole boundary design exists for.
     *
     * The container is left exactly as the observers wrote it — export_ready
     * still 1 — and only the clock moves. Nothing saves, no job runs, and the
     * query must still refuse it.
     */
    public function test_a_reefer_drops_out_the_day_its_pti_lapses_with_no_job_running(): void
    {
        $container = $this->gateIn('XRRF0000002', reefer: true);

        ReeferPtiInspection::create([
            'container_id' => $container->id,
            'inspected_at' => now(),
            'result'       => 'pass',
            'valid_until'  => Carbon::today()->addDays(2),
        ]);
        $container->forceFill(['pti_status' => 'passed', 'pti_at' => now()])->save();

        $this->makeReady($container);
        $container->refresh();

        $this->assertTrue($this->isReturnedByScope($container), 'Precondition: ready today.');

        // Inclusive: the PTI stays valid through the whole of its last day.
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00'));
        $this->assertTrue($this->isReturnedByScope($container),
            'valid_until is inclusive — the last day still counts.');

        // The day after, with no write of any kind in between.
        Carbon::setTestNow(Carbon::parse('2026-08-04 12:00:00'));

        $this->assertTrue((bool) $container->refresh()->export_ready,
            'The stored flag is untouched — nothing saved, so no observer could have fired.');

        $this->assertFalse($this->isReturnedByScope($container),
            'The query compares the stored boundary against today, so the answer is exact without a scheduled recompute.');

        $this->assertTrue($container->mrStatusHasExpired());
        $this->assertTrue(Container::statusExpired()->whereKey($container->id)->exists(),
            'And the row is findable, so a list can overlay the PTI-expired chip.');
    }

    public function test_a_reefer_without_a_pti_is_never_returned(): void
    {
        $container = $this->gateIn('XRRF0000003', reefer: true);
        $this->makeReady($container);

        $this->assertFalse($this->isReturnedByScope($container->refresh()),
            'A reefer with no PTI at all is not exportable.');
    }
}
