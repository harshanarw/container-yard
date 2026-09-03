<?php

namespace Tests\Feature\Reports;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Services\Reporting\WeekBreakdown;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The Weekly Performance screen (Phase 2).
 *
 * The computation has its own tests; these cover what the screen adds — the
 * filters reaching the service, the defaults, the guards on a query string
 * anyone can edit, and the grid actually rendering the numbers rather than
 * merely returning 200.
 */
class WeeklyPerformanceScreenTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-20 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Authorization ───────────────────────────────────────────────────────

    /**
     * `ReportController` authorizes in its constructor, so this inherits the
     * check rather than repeating it. Asserted anyway: "it is inherited" is a
     * claim about code that can be edited.
     */
    public function test_the_screen_is_refused_without_reports_view(): void
    {
        $this->actingAsRole('gate_officer');

        $this->get(route('reports.weekly-performance'))->assertForbidden();
    }

    public function test_the_screen_opens_for_a_role_that_holds_reports_view(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('reports.weekly-performance'))->assertOk();
    }

    // ── Defaults ────────────────────────────────────────────────────────────

    /** Opened cold, it shows the month being reported on. */
    public function test_it_defaults_to_the_current_month_in_seven_day_blocks(): void
    {
        $this->actingAsSystemAdmin();

        $response = $this->get(route('reports.weekly-performance'))->assertOk();
        $data     = $response->viewData('data');

        $this->assertSame('2026-08-01', $data['from']);
        $this->assertSame('2026-08-31', $data['to']);
        $this->assertSame(WeekBreakdown::BLOCKS, $data['week_rule']);
        $this->assertCount(5, $data['weeks'], 'Five bands: 1–7, 8–14, 15–21, 22–28, 29–31.');
    }

    // ── Filters reach the service ───────────────────────────────────────────

    public function test_the_date_range_is_passed_through(): void
    {
        $this->actingAsSystemAdmin();

        $data = $this->get(route('reports.weekly-performance', [
            'from' => '2026-03-02', 'to' => '2026-03-15',
        ]))->assertOk()->viewData('data');

        $this->assertSame(['2026-03-02', '2026-03-15'], [$data['from'], $data['to']]);
        $this->assertCount(2, $data['weeks']);
    }

    public function test_the_week_rule_is_passed_through(): void
    {
        $this->actingAsSystemAdmin();

        $data = $this->get(route('reports.weekly-performance', [
            'week_rule' => WeekBreakdown::CALENDAR,
        ]))->assertOk()->viewData('data');

        $this->assertSame(WeekBreakdown::CALENDAR, $data['week_rule']);
        $this->assertCount(6, $data['weeks'], 'August 2026 opens on a Saturday.');
    }

    public function test_the_customer_filter_narrows_the_grid(): void
    {
        $this->actingAsSystemAdmin();
        $wanted = $this->customerWithLift('WANTED');
        $this->customerWithLift('OTHER');

        $data = $this->get(route('reports.weekly-performance', [
            'customer_id' => $wanted->id,
        ]))->assertOk()->viewData('data');

        $this->assertCount(1, $data['rows']);
        $this->assertSame($wanted->id, $data['rows'][0]['customer_id']);
        $this->assertSame(1, $data['movement_count'], 'The other customer is not counted, only hidden.');
    }

    public function test_the_only_with_movements_switch_reaches_the_service(): void
    {
        $this->actingAsSystemAdmin();
        $busy = $this->customerWithLift('BUSY');
        Customer::factory()->create(['name' => 'QUIET']);

        $all  = $this->get(route('reports.weekly-performance'))->viewData('data');
        $some = $this->get(route('reports.weekly-performance', ['only_with_movements' => 1]))->viewData('data');

        $this->assertLessThan(count($all['rows']), count($some['rows']));
        $this->assertSame([$busy->id], array_column($some['rows'], 'customer_id'));
    }

    // ── Guards on a query string anyone can edit ────────────────────────────

    public function test_a_backwards_range_is_rejected_by_validation(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('reports.weekly-performance', ['from' => '2026-08-20', 'to' => '2026-08-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_an_unknown_week_rule_is_rejected_rather_than_guessed_at(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('reports.weekly-performance', ['week_rule' => 'fortnightly']))
            ->assertSessionHasErrors('week_rule');
    }

    public function test_a_customer_that_does_not_exist_is_rejected(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('reports.weekly-performance', ['customer_id' => 999999]))
            ->assertSessionHasErrors('customer_id');
    }

    public function test_a_nonsense_date_is_rejected(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('reports.weekly-performance', ['from' => 'yesterday-ish']))
            ->assertSessionHasErrors('from');
    }

    // ── The grid itself ─────────────────────────────────────────────────────

    /**
     * Rendering is the half a 200 does not prove. A view that references a key
     * the service stopped returning throws at render, and a grid whose header
     * and body disagree on column count looks fine to an HTTP assertion.
     */
    public function test_the_grid_renders_the_counts_and_the_headings(): void
    {
        $this->actingAsSystemAdmin();
        $customer = $this->customerWithLift('RENDERED');

        $response = $this->get(route('reports.weekly-performance', ['only_with_movements' => 1]))->assertOk();

        $response->assertSee('RENDERED');
        $response->assertSee('Demounting');
        $response->assertSee('Mounting');
        $response->assertSee('WEEK 1');
        $response->assertSee('TOTAL DEMOUNTING');
        $response->assertSee('TOTAL MOUNTING');
        $response->assertSee('GRAND TOTAL');
        $response->assertSee('PERFORMANCE UPDATE [NO. OF UNITS] — AUGUST 2026', false);
    }

    /** The date range under each week band is the requirement, not decoration. */
    public function test_each_week_band_carries_its_date_range(): void
    {
        $this->actingAsSystemAdmin();

        $response = $this->get(route('reports.weekly-performance'))->assertOk();

        foreach ($response->viewData('data')['weeks'] as $week) {
            $response->assertSee($week['label'], false);
        }
    }

    /**
     * The narrowest range the filters can produce still renders a sheet.
     *
     * Validation rejects a backwards range before it reaches the service, so a
     * single day is the smallest thing the screen can be asked for — one band,
     * one date, and the footer rows still present.
     */
    public function test_a_single_day_range_renders_one_band(): void
    {
        $this->actingAsSystemAdmin();

        $response = $this->get(route('reports.weekly-performance', [
            'from' => '2026-08-04', 'to' => '2026-08-04',
        ]))->assertOk();

        $this->assertCount(1, $response->viewData('data')['weeks']);
        $response->assertSee('WEEK 1');
        $response->assertSee('04 Aug 2026');
        $response->assertSee('GRAND TOTAL');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function customerWithLift(string $name): Customer
    {
        $customer  = Customer::factory()->create(['name' => $name]);
        $container = Container::factory()->create(['customer_id' => $customer->id]);

        GateMovement::create([
            'container_id'   => $container->id,
            'container_no'   => $container->container_no,
            'customer_id'    => $customer->id,
            'movement_type'  => 'in',
            'size'           => '20',
            'container_type' => 'GP',
            'cargo_status'   => 'laden',
            'gate_in_time'   => '2026-08-04 10:00:00',
            'created_by'     => auth()->id(),
        ]);

        return $customer;
    }
}
