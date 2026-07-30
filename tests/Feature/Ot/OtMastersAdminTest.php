<?php

namespace Tests\Feature\Ot;

use App\Models\Customer;
use App\Models\Holiday;
use App\Models\OtTariffRule;
use App\Models\OtTariffVersion;
use App\Models\Role;
use App\Models\User;
use App\Models\WeeklyWorkingHour;
use App\Models\WorkingHourSet;
use App\Services\Overtime\OtReceiptService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Overtime module — Phase 6 (masters admin UI). Covers the three setup screens
 * (working hours, holiday calendar, effective-dated tariffs), the setup hub with
 * its resolver dry-run, and the guards that stop a bad edit from silently
 * misbilling or blocking the gate.
 *
 * The assertions that matter most are behavioural: after an admin edits a master,
 * the resolver's verdict for a given date/time must actually change.
 *
 * "Now" is frozen to Monday 01 Jun 2026 so every day-of-week assertion is stable.
 */
class OtMastersAdminTest extends FeatureTestCase
{
    private const MONDAY = '2026-06-01';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::MONDAY . ' 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** What the resolver decides for a date/time, via the admin dry-run endpoint. */
    private function preview(string $date, string $time): array
    {
        return $this->postJson(route('overtime.setup.preview'), [
            'date' => $date, 'time' => $time, 'movement_type' => 'gate_in',
        ])->assertOk()->json();
    }

    /** A full Mon→Sun grid shaped the way the browser posts it (closed days omit their fields). */
    private function daysPayload(array $overrides = []): array
    {
        $days = [];
        foreach (array_keys(WeeklyWorkingHour::DAYS) as $day) {
            $days[$day] = match ($day) {
                // Unticked switch + disabled time inputs → only the policy is submitted.
                'sunday'   => ['after_hours_policy' => 'ot_required'],
                'saturday' => ['is_regular_working_day' => '1', 'normal_start_time' => '08:00',
                               'normal_end_time' => '13:00', 'after_hours_policy' => 'ot_required'],
                default    => ['is_regular_working_day' => '1', 'normal_start_time' => '08:00',
                               'normal_end_time' => '17:00', 'after_hours_policy' => 'ot_required'],
            };
        }

        foreach ($overrides as $day => $row) {
            $days[$day] = $row === null ? ['after_hours_policy' => 'ot_required'] : array_merge($days[$day], $row);
        }

        return $days;
    }

    /**
     * A non-super-user holding exactly $permissions. Effective permissions come from
     * the user_roles pivot, so the role has to be attached as well as named — the
     * factory only sets the role string.
     */
    private function actingAsUserWith(array $permissions): User
    {
        Role::where('name', 'yard_supervisor')->firstOrFail()->syncPermissions($permissions);

        $user = User::factory()->role('yard_supervisor')->create();
        $user->syncRoles(['yard_supervisor']);
        $this->actingAs($user);

        return $user;
    }

    private function setPayload(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Default Working Hours',
            'status'         => 'active',
            'effective_from' => '2026-01-01',
            'is_default'     => '1',
            'days'           => $this->daysPayload(),
        ], $overrides);
    }

    // ── Setup hub + resolver dry-run ─────────────────────────────────────────

    public function test_setup_hub_shows_the_masters_the_engine_is_using(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('overtime.setup.index'))
            ->assertOk()
            ->assertSee('ACDO-OT-2026-04')          // effective tariff version
            ->assertSee('Default Working Hours')     // resolved working-hour set
            ->assertSee('Test the Configuration');   // dry-run panel
    }

    public function test_dry_run_flags_a_weekday_evening_as_overtime_and_offers_both_rates(): void
    {
        $this->actingAsSystemAdmin();

        $out = $this->preview(self::MONDAY, '18:00');

        $this->assertTrue($out['is_overtime']);
        $this->assertFalse($out['within_normal']);
        $this->assertFalse($out['unconfigured']);
        $this->assertSame('weekday', $out['day_category']);

        $this->assertEqualsCanonicalizing(
            ['OT-WD-A', 'OT-WD-B'],
            array_column($out['windows'], 'rule'),
            'A weekday evening should offer both the short (A) and extended (B) periods.'
        );
    }

    public function test_dry_run_reports_normal_hours_inside_the_working_window(): void
    {
        $this->actingAsSystemAdmin();

        $out = $this->preview(self::MONDAY, '10:00');

        $this->assertFalse($out['is_overtime']);
        $this->assertTrue($out['within_normal']);
        $this->assertSame([], $out['windows']);
    }

    public function test_dry_run_reports_an_unconfigured_gap_between_the_tariff_windows(): void
    {
        $this->actingAsSystemAdmin();

        // 06:00 Monday: after the previous day's extended window closed (05:00) but
        // before the yard opens (08:00) — overtime with no rule covering it.
        $out = $this->preview(self::MONDAY, '06:00');

        $this->assertTrue($out['is_overtime']);
        $this->assertTrue($out['unconfigured'], 'The 05:00–08:00 gap should resolve as unconfigured.');
        $this->assertSame([], $out['windows']);
    }

    // ── Working hours ────────────────────────────────────────────────────────

    public function test_working_hour_screens_render(): void
    {
        $this->actingAsSystemAdmin();
        $set = WorkingHourSet::resolved();

        $this->get(route('overtime.working-hours.index'))->assertOk()->assertSee('Default Working Hours');
        $this->get(route('overtime.working-hours.create'))->assertOk()->assertSee('Weekly Schedule');
        $this->get(route('overtime.working-hours.edit', $set))->assertOk()->assertSee('Weekly Schedule');
    }

    public function test_extending_the_working_window_stops_that_time_being_overtime(): void
    {
        $this->actingAsSystemAdmin();
        $set = WorkingHourSet::resolved();

        $this->assertTrue($this->preview(self::MONDAY, '18:00')['is_overtime']);

        $this->patch(route('overtime.working-hours.update', $set), $this->setPayload([
            'days' => $this->daysPayload(['monday' => ['normal_end_time' => '20:00']]),
        ]))->assertSessionHasNoErrors();

        $this->assertStringStartsWith('20:00', (string) $set->refresh()->daysByName()['monday']->normal_end_time);

        // The engine must now read the new window, not the seeded one.
        $out = $this->preview(self::MONDAY, '18:00');
        $this->assertFalse($out['is_overtime'], '18:00 should be normal hours once Monday runs to 20:00.');
        $this->assertTrue($out['within_normal']);
    }

    public function test_closing_a_day_makes_the_whole_day_overtime(): void
    {
        $this->actingAsSystemAdmin();
        $set = WorkingHourSet::resolved();

        $this->patch(route('overtime.working-hours.update', $set), $this->setPayload([
            'days' => $this->daysPayload(['monday' => null]), // untick Monday → closed
        ]))->assertSessionHasNoErrors();

        $monday = $set->refresh()->daysByName()['monday'];
        $this->assertFalse((bool) $monday->is_regular_working_day);
        $this->assertNull($monday->normal_start_time, 'A closed day must not keep a stale window.');

        $this->assertTrue($this->preview(self::MONDAY, '10:00')['is_overtime']);
    }

    public function test_a_working_day_without_times_is_rejected(): void
    {
        $this->actingAsSystemAdmin();
        $set = WorkingHourSet::resolved();

        $this->from(route('overtime.working-hours.edit', $set))
            ->patch(route('overtime.working-hours.update', $set), $this->setPayload([
                'days' => $this->daysPayload(['tuesday' => ['normal_start_time' => '', 'normal_end_time' => '']]),
            ]))
            ->assertSessionHasErrors('days.tuesday.normal_start_time');
    }

    public function test_an_end_time_before_the_start_time_is_rejected(): void
    {
        $this->actingAsSystemAdmin();
        $set = WorkingHourSet::resolved();

        $this->from(route('overtime.working-hours.edit', $set))
            ->patch(route('overtime.working-hours.update', $set), $this->setPayload([
                'days' => $this->daysPayload(['tuesday' => ['normal_start_time' => '17:00', 'normal_end_time' => '08:00']]),
            ]))
            ->assertSessionHasErrors('days.tuesday.normal_end_time');
    }

    public function test_a_set_with_every_day_closed_is_rejected(): void
    {
        $this->actingAsSystemAdmin();
        $set = WorkingHourSet::resolved();

        $allClosed = [];
        foreach (array_keys(WeeklyWorkingHour::DAYS) as $day) {
            $allClosed[$day] = null;
        }

        $this->from(route('overtime.working-hours.edit', $set))
            ->patch(route('overtime.working-hours.update', $set), $this->setPayload([
                'days' => $this->daysPayload($allClosed),
            ]))
            ->assertSessionHasErrors('days.monday.is_regular_working_day');
    }

    public function test_the_set_in_use_cannot_be_deleted(): void
    {
        $this->actingAsSystemAdmin();
        $set = WorkingHourSet::resolved();

        $this->delete(route('overtime.working-hours.destroy', $set))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('working_hour_sets', ['id' => $set->id]);
    }

    public function test_making_another_set_default_unflags_the_previous_one(): void
    {
        $this->actingAsSystemAdmin();
        $original = WorkingHourSet::resolved();

        $this->post(route('overtime.working-hours.store'), $this->setPayload([
            'name'       => 'Peak Season Hours',
            'is_default' => '1',
        ]))->assertSessionHasNoErrors();

        $new = WorkingHourSet::where('name', 'Peak Season Hours')->firstOrFail();

        $this->assertTrue((bool) $new->is_default);
        $this->assertFalse((bool) $original->refresh()->is_default, 'Only one set may be the default.');
        $this->assertTrue(WorkingHourSet::resolved()->is($new));
        $this->assertCount(7, $new->days);
    }

    public function test_a_duplicate_set_name_is_rejected(): void
    {
        $this->actingAsSystemAdmin();

        $this->from(route('overtime.working-hours.create'))
            ->post(route('overtime.working-hours.store'), $this->setPayload(['name' => 'Default Working Hours']))
            ->assertSessionHasErrors('name');
    }

    // ── Holiday calendar ─────────────────────────────────────────────────────

    public function test_holiday_calendar_renders_the_seeded_year(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('overtime.holidays.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Christmas Day')
            ->assertSee('at a Glance');
    }

    public function test_adding_a_holiday_switches_that_day_to_the_holiday_rate(): void
    {
        $this->actingAsSystemAdmin();

        // A plain Wednesday, before: weekday, working hours 08:00–17:00.
        $before = $this->preview('2026-06-03', '10:00');
        $this->assertSame('weekday', $before['day_category']);
        $this->assertFalse($before['is_overtime']);

        $this->post(route('overtime.holidays.store'), [
            'holiday_date'          => '2026-06-03',
            'holiday_name'          => 'Special Depot Holiday',
            'holiday_type'          => 'mercantile',
            'is_mercantile'         => '1',
            'working_hour_override' => 'closed',
            'active'                => '1',
        ])->assertSessionHasNoErrors();

        $after = $this->preview('2026-06-03', '10:00');
        $this->assertSame('sunday_mercantile_holiday', $after['day_category']);
        $this->assertTrue($after['is_overtime'], 'A closed holiday makes the whole day overtime.');
        $this->assertEqualsCanonicalizing(['OT-HOL-A', 'OT-HOL-B'], array_column($after['windows'], 'rule'));
        $this->assertSame('Special Depot Holiday', $after['holiday']);
    }

    public function test_custom_holiday_hours_are_honoured_by_the_engine(): void
    {
        $this->actingAsSystemAdmin();

        $this->post(route('overtime.holidays.store'), [
            'holiday_date'          => '2026-06-03',
            'holiday_name'          => 'Half-Day Holiday',
            'holiday_type'          => 'company_special',
            'working_hour_override' => 'custom',
            'custom_start_time'     => '08:00',
            'custom_end_time'       => '12:00',
            'active'                => '1',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($this->preview('2026-06-03', '10:00')['is_overtime'], 'Inside the custom window.');
        $this->assertTrue($this->preview('2026-06-03', '14:00')['is_overtime'], 'Outside the custom window.');
    }

    public function test_custom_holiday_hours_require_both_times(): void
    {
        $this->actingAsSystemAdmin();

        $this->from(route('overtime.holidays.index'))
            ->post(route('overtime.holidays.store'), [
                'holiday_date'          => '2026-06-04',
                'holiday_name'          => 'Broken Custom Holiday',
                'holiday_type'          => 'public',
                'working_hour_override' => 'custom',
                'custom_start_time'     => '08:00',
                'active'                => '1',
            ])
            ->assertSessionHasErrors('custom_start_time');

        $this->assertDatabaseMissing('holidays', ['holiday_date' => '2026-06-04']);
    }

    public function test_a_duplicate_holiday_date_is_rejected(): void
    {
        $this->actingAsSystemAdmin();

        $this->from(route('overtime.holidays.index'))
            ->post(route('overtime.holidays.store'), [
                'holiday_date'          => '2026-12-25', // already seeded
                'holiday_name'          => 'Duplicate Christmas',
                'holiday_type'          => 'public',
                'working_hour_override' => 'closed',
                'active'                => '1',
            ])
            ->assertSessionHasErrors('holiday_date');
    }

    public function test_deactivating_a_holiday_returns_the_day_to_its_weekday_rate(): void
    {
        $this->actingAsSystemAdmin();
        $holiday = Holiday::where('holiday_date', '2026-04-14')->firstOrFail();

        $this->assertSame('sunday_mercantile_holiday', $this->preview('2026-04-14', '10:00')['day_category']);

        $this->patch(route('overtime.holidays.toggle', $holiday))->assertSessionHas('success');

        $this->assertFalse((bool) $holiday->refresh()->active);
        // 14 Apr 2026 is a Tuesday.
        $this->assertSame('weekday', $this->preview('2026-04-14', '10:00')['day_category']);
    }

    // ── Tariff versions & rules ──────────────────────────────────────────────

    public function test_tariff_screens_render(): void
    {
        $this->actingAsSystemAdmin();
        $version = OtTariffVersion::where('version_code', 'ACDO-OT-2026-04')->firstOrFail();

        $this->get(route('overtime.tariffs.index'))->assertOk()->assertSee('ACDO-OT-2026-04');
        $this->get(route('overtime.tariffs.create'))->assertOk()->assertSee('Version Code');
        $this->get(route('overtime.tariffs.show', $version))->assertOk()->assertSee('OT-WD-A')->assertSee('Rate Rules');
    }

    public function test_a_new_version_is_a_draft_and_needs_a_rule_before_it_can_activate(): void
    {
        $this->actingAsSystemAdmin();

        $this->post(route('overtime.tariffs.store'), [
            'version_code'    => 'ACDO-OT-2027-01',
            'name'            => 'ACDO Revised Depot OT 2027',
            'effective_from'  => '2027-01-01',
            'currency'        => 'lkr',
            'approval_status' => 'draft',
            'active'          => '1',
        ])->assertSessionHasNoErrors();

        $version = OtTariffVersion::where('version_code', 'ACDO-OT-2027-01')->firstOrFail();
        $this->assertSame('draft', $version->approval_status);
        $this->assertSame('LKR', $version->currency, 'Currency should be normalised to upper case.');

        // No rules yet → activation is refused.
        $this->patch(route('overtime.tariffs.activate', $version))->assertSessionHas('error');
        $this->assertSame('draft', $version->refresh()->approval_status);

        $this->post(route('overtime.tariffs.rules.store', $version), [
            'rule_code'                 => 'OT27-WD-A',
            'display_name'              => 'Weekday 17:00–24:00',
            'movement_type'             => 'gate_in',
            'day_category'              => 'weekday',
            'period_code'               => 'a',
            'start_time'                => '17:00',
            'end_time'                  => '00:00',
            'ends_next_day'             => '1',
            'rate_amount'               => '12500',
            'charge_basis'              => 'per_bl_receipt',
            'billing_mode_on_extension' => 'full_new_charge',
            'priority'                  => '1',
            'active'                    => '1',
        ])->assertSessionHasNoErrors();

        $rule = OtTariffRule::where('rule_code', 'OT27-WD-A')->firstOrFail();
        $this->assertTrue((bool) $rule->ends_next_day);
        $this->assertSame('LKR', $rule->currency, 'A rule inherits its version currency.');

        $this->patch(route('overtime.tariffs.activate', $version))->assertSessionHas('success');
        $this->assertSame('active', $version->refresh()->approval_status);
    }

    public function test_activating_a_version_closes_the_previous_open_ended_one(): void
    {
        $this->actingAsSystemAdmin();
        $seeded = OtTariffVersion::where('version_code', 'ACDO-OT-2026-04')->firstOrFail();
        $this->assertNull($seeded->effective_to);

        $next = OtTariffVersion::create([
            'version_code' => 'ACDO-OT-2026-08', 'name' => 'Revised August rates',
            'effective_from' => '2026-08-01', 'currency' => 'LKR',
            'approval_status' => 'draft', 'active' => true,
        ]);
        $next->rules()->create([
            'rule_code' => 'OT08-WD-A', 'display_name' => 'Weekday evening', 'movement_type' => 'gate_in',
            'day_category' => 'weekday', 'period_code' => 'a', 'start_time' => '17:00', 'end_time' => '00:00',
            'ends_next_day' => true, 'rate_amount' => 18000, 'currency' => 'LKR',
            'charge_basis' => 'per_bl_receipt', 'billing_mode_on_extension' => 'full_new_charge',
            'priority' => 1, 'active' => true,
        ]);

        $this->patch(route('overtime.tariffs.activate', $next))->assertSessionHas('success');

        $this->assertSame('2026-07-31', $seeded->refresh()->effective_to->toDateString(),
            'The superseded version should be closed the day before the new one starts.');

        // Before the cutover the old rates apply; after it, the new one.
        // Both probe dates are weekdays (15 Jul and 17 Aug 2026 are Wed and Mon).
        $this->assertEqualsCanonicalizing(
            ['OT-WD-A', 'OT-WD-B'], array_column($this->preview('2026-07-15', '18:00')['windows'], 'rule')
        );
        $this->assertSame(
            ['OT08-WD-A'], array_column($this->preview('2026-08-17', '18:00')['windows'], 'rule')
        );
    }

    public function test_a_window_that_ends_before_it_starts_needs_the_next_day_flag(): void
    {
        $this->actingAsSystemAdmin();
        $version = OtTariffVersion::create([
            'version_code' => 'TMP-1', 'name' => 'Scratch', 'effective_from' => '2027-01-01',
            'currency' => 'LKR', 'approval_status' => 'draft', 'active' => true,
        ]);

        $this->from(route('overtime.tariffs.show', $version))
            ->post(route('overtime.tariffs.rules.store', $version), [
                'rule_code' => 'BAD-1', 'display_name' => 'Backwards window', 'movement_type' => 'gate_in',
                'day_category' => 'weekday', 'period_code' => 'a', 'start_time' => '17:00', 'end_time' => '05:00',
                'rate_amount' => '1000', 'charge_basis' => 'per_bl_receipt',
                'billing_mode_on_extension' => 'full_new_charge', 'priority' => '1', 'active' => '1',
            ])
            ->assertSessionHasErrors('end_time');

        $this->assertDatabaseMissing('ot_tariff_rules', ['rule_code' => 'BAD-1']);
    }

    public function test_a_second_rule_for_the_same_category_and_period_is_rejected(): void
    {
        $this->actingAsSystemAdmin();
        $version = OtTariffVersion::where('version_code', 'ACDO-OT-2026-04')->firstOrFail();

        $this->from(route('overtime.tariffs.show', $version))
            ->post(route('overtime.tariffs.rules.store', $version), [
                'rule_code' => 'OT-WD-A2', 'display_name' => 'Duplicate weekday A', 'movement_type' => 'gate_in',
                'day_category' => 'weekday', 'period_code' => 'a', 'start_time' => '18:00', 'end_time' => '23:00',
                'rate_amount' => '9000', 'charge_basis' => 'per_bl_receipt',
                'billing_mode_on_extension' => 'full_new_charge', 'priority' => '3', 'active' => '1',
            ])
            ->assertSessionHasErrors('period_code');
    }

    public function test_cloning_copies_every_rule_and_applies_a_rate_change(): void
    {
        $this->actingAsSystemAdmin();
        $source = OtTariffVersion::where('version_code', 'ACDO-OT-2026-04')->firstOrFail();

        $this->post(route('overtime.tariffs.clone', $source), [
            'version_code'    => 'ACDO-OT-2027-04',
            'name'            => 'ACDO Revised Depot OT 2027',
            'effective_from'  => '2027-04-01',
            'rate_change_pct' => '10',
        ])->assertSessionHas('success');

        $clone = OtTariffVersion::where('version_code', 'ACDO-OT-2027-04')->firstOrFail();

        $this->assertSame('draft', $clone->approval_status, 'A clone must be reviewed before it can bill.');
        $this->assertSame($source->rules()->count(), $clone->rules()->count());
        $this->assertEqualsWithDelta(
            11000, // 10000 + 10%
            (float) $clone->rules()->where('rule_code', 'OT-WD-A')->value('rate_amount'),
            0.001
        );
        // The source is untouched.
        $this->assertEqualsWithDelta(10000, (float) $source->rules()->where('rule_code', 'OT-WD-A')->value('rate_amount'), 0.001);
    }

    public function test_a_version_that_has_issued_receipts_is_read_only(): void
    {
        $this->actingAsSystemAdmin();
        $version = OtTariffVersion::where('version_code', 'ACDO-OT-2026-04')->firstOrFail();
        $rule    = $version->rules()->where('rule_code', 'OT-WD-A')->firstOrFail();

        app(OtReceiptService::class)->generate([
            'bl_number'                => 'CMBLOCK01',
            'customer_id'              => Customer::factory()->create()->id,
            'operational_date'         => self::MONDAY,
            'tariff_rule_id'           => $rule->id,
            'expected_container_count' => 1,
        ]);

        $this->assertTrue($version->refresh()->isLocked());

        // Rate edits, deletes and header edits are all refused.
        $this->patch(route('overtime.tariffs.rules.update', [$version, $rule]), [
            'rule_code' => 'OT-WD-A', 'display_name' => 'Sneaky rate rise', 'movement_type' => 'gate_in',
            'day_category' => 'weekday', 'period_code' => 'a', 'start_time' => '17:00', 'end_time' => '00:00',
            'ends_next_day' => '1', 'rate_amount' => '99999', 'charge_basis' => 'per_bl_receipt',
            'billing_mode_on_extension' => 'full_new_charge', 'priority' => '1', 'active' => '1',
        ])->assertSessionHas('error');

        $this->assertEqualsWithDelta(10000, (float) $rule->refresh()->rate_amount, 0.001,
            'A billed rate must not change under an issued receipt.');

        $this->delete(route('overtime.tariffs.rules.destroy', [$version, $rule]))->assertSessionHas('error');
        $this->assertDatabaseHas('ot_tariff_rules', ['id' => $rule->id]);

        $this->delete(route('overtime.tariffs.destroy', $version))->assertSessionHas('error');
        $this->assertDatabaseHas('ot_tariff_versions', ['id' => $version->id]);
    }

    public function test_the_only_effective_version_cannot_be_retired(): void
    {
        $this->actingAsSystemAdmin();
        $version = OtTariffVersion::where('version_code', 'ACDO-OT-2026-04')->firstOrFail();

        $this->patch(route('overtime.tariffs.retire', $version))->assertSessionHas('error');
        $this->assertSame('active', $version->refresh()->approval_status);
    }

    public function test_a_rule_from_another_version_is_not_reachable(): void
    {
        $this->actingAsSystemAdmin();
        $other = OtTariffVersion::create([
            'version_code' => 'TMP-2', 'name' => 'Scratch', 'effective_from' => '2027-01-01',
            'currency' => 'LKR', 'approval_status' => 'draft', 'active' => true,
        ]);
        $rule = OtTariffRule::where('rule_code', 'OT-WD-A')->firstOrFail(); // belongs to the ACDO version

        $this->patch(route('overtime.tariffs.rules.toggle', [$other, $rule]))->assertNotFound();
    }

    // ── Permissions ──────────────────────────────────────────────────────────

    public function test_view_only_users_cannot_change_the_masters(): void
    {
        $this->actingAsUserWith(['ot.settings.view']);

        $set = WorkingHourSet::resolved();

        // Reading is allowed …
        $this->get(route('overtime.setup.index'))->assertOk();
        $this->get(route('overtime.working-hours.index'))->assertOk();
        $this->get(route('overtime.holidays.index'))->assertOk();
        $this->get(route('overtime.tariffs.index'))->assertOk();

        // … writing is not.
        $this->get(route('overtime.working-hours.create'))->assertForbidden();
        $this->patch(route('overtime.working-hours.update', $set), $this->setPayload())->assertForbidden();
        $this->post(route('overtime.holidays.store'), [
            'holiday_date' => '2026-06-05', 'holiday_name' => 'Nope',
            'holiday_type' => 'public', 'working_hour_override' => 'closed',
        ])->assertForbidden();
        $this->post(route('overtime.tariffs.store'), [
            'version_code' => 'NOPE-1', 'name' => 'Nope', 'effective_from' => '2027-01-01',
            'currency' => 'LKR', 'approval_status' => 'draft',
        ])->assertForbidden();

        $this->assertDatabaseMissing('holidays', ['holiday_date' => '2026-06-05']);
        $this->assertDatabaseMissing('ot_tariff_versions', ['version_code' => 'NOPE-1']);
    }

    public function test_an_editor_without_approval_rights_cannot_activate_a_version(): void
    {
        $this->actingAsUserWith(['ot.settings.view', 'ot.settings.edit']);

        $version = OtTariffVersion::where('version_code', 'ACDO-OT-2026-04')->firstOrFail();
        $this->patch(route('overtime.tariffs.activate', $version))->assertForbidden();

        // Nor by saving the header straight into "active".
        $this->from(route('overtime.tariffs.create'))
            ->post(route('overtime.tariffs.store'), [
                'version_code' => 'SNEAK-1', 'name' => 'Straight to active', 'effective_from' => '2027-01-01',
                'currency' => 'LKR', 'approval_status' => 'active', 'active' => '1',
            ])
            ->assertSessionHasErrors('approval_status');

        $this->assertDatabaseMissing('ot_tariff_versions', ['version_code' => 'SNEAK-1']);
    }

    // ── Enforcement toggle ───────────────────────────────────────────────────

    public function test_the_enforcement_toggle_saves_from_company_settings(): void
    {
        $this->actingAsSystemAdmin();

        $this->assertFalse((bool) \App\Models\CompanySetting::current()->require_ot_receipt);

        $this->from(route('settings.company.index'))
            ->post(route('settings.company.update'), [
                'company_name'       => 'Test Yard',
                'require_ot_receipt' => '1',
            ])->assertSessionHasNoErrors();

        $this->assertTrue((bool) \App\Models\CompanySetting::current()->require_ot_receipt,
            'The OT enforcement switch should be persisted by the company settings form.');

        // And off again when the switch is cleared.
        $this->post(route('settings.company.update'), ['company_name' => 'Test Yard'])
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) \App\Models\CompanySetting::current()->require_ot_receipt);
    }
}
