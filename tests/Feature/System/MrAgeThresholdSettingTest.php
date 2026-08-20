<?php

namespace Tests\Feature\System;

use App\Models\CompanySetting;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\Estimate;
use App\Models\WorkOrder;
use App\Models\YardJobType;
use App\Services\ContainerMrStatusService;
use App\Support\MrStatusCatalogue as Cat;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Operator-configurable "overdue" thresholds.
 *
 * The shipped numbers in MrStatusCatalogue are a starting point, not a
 * measurement of any real yard, so they have to be tunable without a deploy.
 * Company Settings holds a JSON map that merges over the defaults key by key.
 */
class MrAgeThresholdSettingTest extends FeatureTestCase
{
    private static int $woSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 12:00:00');
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): ContainerMrStatusService
    {
        // A fresh instance each time: the service memoises the thresholds for
        // the request, which is right in production and wrong across a test
        // that changes the setting.
        return new ContainerMrStatusService();
    }

    // ── Resolution of the effective map ──────────────────────────────────────

    public function test_the_shipped_defaults_apply_when_nothing_is_configured(): void
    {
        CompanySetting::current()->forceFill(['mr_age_thresholds' => null])->save();
        CompanySetting::flushCache();

        $this->assertSame(
            Cat::AGE_THRESHOLD_DAYS[Cat::AWAITING_QC],
            $this->service()->ageThresholds()[Cat::AWAITING_QC]
        );
    }

    public function test_a_configured_value_overrides_only_its_own_stage(): void
    {
        CompanySetting::current()->forceFill([
            'mr_age_thresholds' => [Cat::AWAITING_QC => 1],
        ])->save();
        CompanySetting::flushCache();

        $thresholds = $this->service()->ageThresholds();

        $this->assertSame(1, $thresholds[Cat::AWAITING_QC]);
        $this->assertSame(
            Cat::AGE_THRESHOLD_DAYS[Cat::REPAIR_IN_PROGRESS],
            $thresholds[Cat::REPAIR_IN_PROGRESS],
            'Tuning one stage must not silently reset the others.'
        );
    }

    public function test_junk_values_are_ignored_rather_than_stored(): void
    {
        CompanySetting::current()->forceFill([
            'mr_age_thresholds' => [
                Cat::AWAITING_QC => 0,          // would flag everything instantly
                Cat::PTI_DUE     => -5,
                'not_a_status'   => 9,
            ],
        ])->save();
        CompanySetting::flushCache();

        $thresholds = $this->service()->ageThresholds();

        $this->assertSame(Cat::AGE_THRESHOLD_DAYS[Cat::AWAITING_QC], $thresholds[Cat::AWAITING_QC]);
        $this->assertSame(Cat::AGE_THRESHOLD_DAYS[Cat::PTI_DUE], $thresholds[Cat::PTI_DUE]);
        $this->assertArrayNotHasKey('not_a_status', $thresholds);
    }

    // ── The threshold actually drives the flag ───────────────────────────────

    public function test_a_configured_threshold_changes_what_is_flagged_overdue(): void
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)->where('job_type_code', '!=', 'EMPTY_RETURN')->first();
        $equipment = EquipmentType::all()->first(fn ($e) => ! $e->isReefer())
            ?? EquipmentType::query()->firstOrFail();
        $customer = Customer::factory()->create();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => 'AGET0000001',
            'equipment_type_id' => $equipment->id,
            'customer_id'       => $customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => '2026-11-02 08:00:00',
        ])->assertSessionHasNoErrors();

        $container = Container::where('container_no', 'AGET0000001')->firstOrFail();

        // Finished two days ago and waiting on QC ever since.
        WorkOrder::create([
            'wo_no'        => 'WO-AG' . str_pad((string) ++self::$woSeq, 5, '0', STR_PAD_LEFT),
            'estimate_id'  => Estimate::query()->firstOrFail()->id,
            'container_id' => $container->id,
            'container_no' => $container->container_no,
            'customer_id'  => $customer->id,
            'status'       => 'completed',
        ])->update(['completed_date' => Carbon::now()->subDays(2)]);

        // Default for awaiting QC is 3 days — two days in is not yet overdue.
        CompanySetting::current()->forceFill(['mr_age_thresholds' => null])->save();
        CompanySetting::flushCache();

        $status = $this->service()->forContainer($container->refresh());
        $this->assertSame(Cat::AWAITING_QC, $status->code);
        $this->assertFalse($status->isOverdue());

        // Tighten it to one day and the same container is now flagged.
        CompanySetting::current()->forceFill([
            'mr_age_thresholds' => [Cat::AWAITING_QC => 1],
        ])->save();
        CompanySetting::flushCache();

        $this->assertTrue($this->service()->forContainer($container)->isOverdue(),
            'The setting has to reach the flag, not just the settings screen.');
    }

    // ── The settings screen ──────────────────────────────────────────────────

    public function test_the_settings_screen_saves_thresholds(): void
    {
        $this->post(route('settings.company.update'), [
            'company_name'      => 'Container Yard Management',
            'mr_age_thresholds' => [Cat::AWAITING_QC => 9],
        ])->assertSessionHasNoErrors();

        CompanySetting::flushCache();

        $this->assertSame(9, CompanySetting::current()->mr_age_thresholds[Cat::AWAITING_QC]);
    }

    public function test_a_blank_box_falls_back_to_the_default(): void
    {
        $this->post(route('settings.company.update'), [
            'company_name'      => 'Container Yard Management',
            'mr_age_thresholds' => [Cat::AWAITING_QC => '', Cat::PTI_DUE => 4],
        ])->assertSessionHasNoErrors();

        CompanySetting::flushCache();

        $saved = CompanySetting::current()->mr_age_thresholds;

        $this->assertArrayNotHasKey(Cat::AWAITING_QC, $saved, 'Blank means "use the default", not "store nothing".');
        $this->assertSame(4, $saved[Cat::PTI_DUE]);
        $this->assertSame(Cat::AGE_THRESHOLD_DAYS[Cat::AWAITING_QC], $this->service()->ageThresholds()[Cat::AWAITING_QC]);
    }

    public function test_out_of_range_values_are_rejected(): void
    {
        $this->post(route('settings.company.update'), [
            'company_name'      => 'Container Yard Management',
            'mr_age_thresholds' => [Cat::AWAITING_QC => 0],
        ])->assertSessionHasErrors('mr_age_thresholds.' . Cat::AWAITING_QC);
    }

    /**
     * Several forms on the company settings page post to this same action —
     * the logo, icon and product-icon uploads each send only company_name and
     * their file. Writing the threshold key unconditionally would wipe every
     * configured value the next time someone uploaded a logo.
     */
    public function test_a_submission_without_thresholds_leaves_them_alone(): void
    {
        CompanySetting::current()->forceFill([
            'mr_age_thresholds' => [Cat::AWAITING_QC => 9],
        ])->save();
        CompanySetting::flushCache();

        $this->post(route('settings.company.update'), [
            'company_name' => 'Container Yard Management',
        ])->assertSessionHasNoErrors();

        CompanySetting::flushCache();

        $this->assertSame(9, CompanySetting::current()->mr_age_thresholds[Cat::AWAITING_QC] ?? null,
            'An unrelated save must not clear the thresholds.');
    }

    public function test_the_settings_screen_renders_the_thresholds(): void
    {
        $this->get(route('settings.company.index'))
             ->assertOk()
             ->assertSee('M&amp;R Overdue Thresholds', false)
             ->assertSee('mr_age_thresholds[' . Cat::AWAITING_QC . ']', false);
    }
}
