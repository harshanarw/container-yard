<?php

namespace Tests\Feature\Ot;

use App\Models\OtTariffRule;
use App\Services\Overtime\OvertimeRuleResolver;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Overtime module — Phase 2. Exercises the OvertimeRuleResolver against the SRS
 * scenario matrix (TC-001…TC-011): day-category resolution, normal-hours
 * detection, applicable tariff windows and next-day rollover.
 */
class OvertimeRuleResolverTest extends FeatureTestCase
{
    private OvertimeRuleResolver $r;
    private Carbon $monday;
    private Carbon $saturday;
    private Carbon $sunday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->r = new OvertimeRuleResolver();
        // A clean week fully after the tariff's 2026-04-01 effective date, with no
        // seeded holiday (June has none of the seeded 2026 mercantile holidays).
        $this->monday = Carbon::parse('2026-06-01')->startOfWeek(Carbon::MONDAY);
        $this->assertTrue($this->monday->isMonday());
        $this->saturday = $this->monday->copy()->addDays(5);
        $this->sunday   = $this->monday->copy()->addDays(6);
    }

    private function codes(Carbon $dt): array
    {
        return $this->r->getApplicableWindows($dt)->map(fn ($w) => $w['rule']->rule_code)->all();
    }

    public function test_tc001_weekday_normal_hours_not_overtime(): void
    {
        $dt = $this->monday->copy()->setTime(10, 0);
        $this->assertSame('weekday', $this->r->resolveDayCategory($this->monday));
        $this->assertTrue($this->r->isWithinNormalWorkingHours($dt));
        $this->assertFalse($this->r->isOvertime($dt));
    }

    public function test_tc002_003_weekday_evening_offers_A_and_B(): void
    {
        $dt = $this->monday->copy()->setTime(18, 0);
        $this->assertTrue($this->r->isOvertime($dt));
        $codes = $this->codes($dt);
        $this->assertContains('OT-WD-A', $codes);
        $this->assertContains('OT-WD-B', $codes);
    }

    public function test_tc004_005_after_midnight_matches_previous_day_B_not_A(): void
    {
        $dt = $this->monday->copy()->addDay()->setTime(2, 0); // Tuesday 02:00
        $this->assertTrue($this->r->isOvertime($dt));

        $codes = $this->codes($dt);
        $this->assertContains('OT-WD-B', $codes, 'B covers 17:00–05:00 next day.');
        $this->assertNotContains('OT-WD-A', $codes, 'A ends at 24:00 — must not cover 02:00.');

        // The A window itself does not include 02:00.
        $ruleA = OtTariffRule::where('rule_code', 'OT-WD-A')->first();
        $win   = $this->r->buildValidityWindow($ruleA, $this->monday->copy());
        $this->assertFalse($dt->gte($win['from']) && $dt->lte($win['to']));
    }

    public function test_tc006_saturday_normal_half_day_not_overtime(): void
    {
        $dt = $this->saturday->copy()->setTime(12, 0);
        $this->assertSame('saturday', $this->r->resolveDayCategory($this->saturday));
        $this->assertTrue($this->r->isWithinNormalWorkingHours($dt)); // Sat 08:00–13:00
    }

    public function test_tc007_saturday_afternoon_offers_sat_A_and_B(): void
    {
        $dt = $this->saturday->copy()->setTime(14, 0);
        $this->assertTrue($this->r->isOvertime($dt));
        $codes = $this->codes($dt);
        $this->assertContains('OT-SAT-A', $codes);
        $this->assertContains('OT-SAT-B', $codes);
    }

    public function test_tc008_saturday_night_only_sat_B(): void
    {
        $dt = $this->saturday->copy()->setTime(21, 0);
        $codes = $this->codes($dt);
        $this->assertContains('OT-SAT-B', $codes);
        $this->assertNotContains('OT-SAT-A', $codes, 'Saturday A ends at 17:00.');
    }

    public function test_tc009_sunday_day_offers_holiday_rule(): void
    {
        $dt = $this->sunday->copy()->setTime(10, 0);
        $this->assertSame('sunday_mercantile_holiday', $this->r->resolveDayCategory($this->sunday));
        $this->assertTrue($this->r->isOvertime($dt));
        $this->assertContains('OT-HOL-A', $this->codes($dt));
    }

    public function test_tc010_mercantile_holiday_uses_holiday_not_weekday_rule(): void
    {
        $holiday = Carbon::parse('2026-04-14'); // seeded mercantile holiday
        $this->assertSame('sunday_mercantile_holiday', $this->r->resolveDayCategory($holiday));

        $codes = $this->codes($holiday->copy()->setTime(10, 0));
        $this->assertContains('OT-HOL-A', $codes);
        $this->assertNotContains('OT-WD-A', $codes, 'Weekday rules must not apply on a mercantile holiday.');
    }

    public function test_tc011_early_morning_gap_is_unconfigured(): void
    {
        $dt = $this->monday->copy()->setTime(6, 0); // 05:00–08:00 gap, weekday
        $this->assertTrue($this->r->isOvertime($dt));

        $res = $this->r->resolve($dt);
        $this->assertTrue($res['unconfigured']);
        $this->assertTrue($res['windows']->isEmpty());
    }

    public function test_resolve_summary_for_a_covered_ot_moment(): void
    {
        $res = $this->r->resolve($this->monday->copy()->setTime(18, 0));
        $this->assertTrue($res['is_overtime']);
        $this->assertFalse($res['within_normal']);
        $this->assertFalse($res['unconfigured']);
        $this->assertTrue($res['windows']->isNotEmpty());
    }
}
