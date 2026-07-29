<?php

namespace Tests\Feature\Ot;

use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\OtTariffVersion;
use App\Models\WeeklyWorkingHour;
use App\Models\WorkingHourSet;
use App\Services\NumberSequenceService;
use Tests\Support\FeatureTestCase;

/**
 * Overtime module — Phase 1 (masters + seeders). Verifies the default working
 * hours, holidays, ACDO tariff version/rules, OT income account, OTR sequence,
 * the require_ot_receipt setting, and the synced OT permissions.
 */
class OtMasterSeedTest extends FeatureTestCase
{
    public function test_default_weekly_working_hours_are_seeded(): void
    {
        $set = WorkingHourSet::where('is_default', true)->first();
        $this->assertNotNull($set, 'Default working-hour set should be seeded.');

        $days = WeeklyWorkingHour::where('working_hour_set_id', $set->id)->get()->keyBy('day_of_week');
        $this->assertCount(7, $days);

        $this->assertStringStartsWith('08:00', (string) $days['monday']->normal_start_time);
        $this->assertStringStartsWith('17:00', (string) $days['monday']->normal_end_time);
        $this->assertStringStartsWith('13:00', (string) $days['saturday']->normal_end_time); // half-day
        $this->assertFalse((bool) $days['sunday']->is_regular_working_day);
        $this->assertNull($days['sunday']->normal_start_time);                                // closed
    }

    public function test_acdo_tariff_version_and_rules_are_seeded(): void
    {
        $version = OtTariffVersion::where('version_code', 'ACDO-OT-2026-04')->first();
        $this->assertNotNull($version);

        $rules = $version->rules()->get()->keyBy('rule_code');
        $this->assertCount(6, $rules);

        $this->assertEqualsWithDelta(10000, (float) $rules['OT-WD-A']->rate_amount, 0.001);
        $this->assertEqualsWithDelta(15000, (float) $rules['OT-WD-B']->rate_amount, 0.001);
        $this->assertEqualsWithDelta(22000, (float) $rules['OT-SAT-B']->rate_amount, 0.001);
        $this->assertEqualsWithDelta(30000, (float) $rules['OT-HOL-B']->rate_amount, 0.001);

        $this->assertTrue((bool) $rules['OT-WD-B']->ends_next_day);
        $this->assertFalse((bool) $rules['OT-SAT-A']->ends_next_day);
        $this->assertSame('sunday_mercantile_holiday', $rules['OT-HOL-A']->day_category);
    }

    public function test_ot_income_account_sequence_and_setting(): void
    {
        $account = Account::where('code', '4009')->first();
        $this->assertNotNull($account, 'OT revenue account 4009 should exist.');
        $this->assertSame('income', $account->classification);
        $this->assertSame('credit', $account->normal_balance);

        $this->assertStringContainsString('OTR', app(NumberSequenceService::class)->generate('ot_receipt'));

        $this->assertFalse((bool) CompanySetting::current()->require_ot_receipt); // opt-in, default off
    }

    public function test_ot_permissions_and_holidays_are_seeded(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'ot.receipt.generate']);
        $this->assertDatabaseHas('permissions', ['name' => 'ot.settings.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'gatein.ot.override']);

        $this->assertDatabaseHas('holidays', ['holiday_date' => '2026-04-14', 'is_mercantile' => true]);
    }
}
