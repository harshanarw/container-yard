<?php

namespace Tests\Feature\Reports;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateCheckReview;
use App\Models\GateMovement;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Diagnostics\GateDataCheck;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Gate Data Check — the three findings, and everything that must *not* be one.
 *
 * Half this file is about what the screen stays quiet about. A diagnostic that
 * flags ordinary yard work is worse than none: people learn to ignore it, and
 * then the real finding goes unread too.
 */
class GateDataCheckTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-04 12:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The three findings ──────────────────────────────────────────────────

    /** As `MEDU8724659` is on the live system: recorded 28 Aug, dated 7 Sep. */
    public function test_it_finds_a_gate_in_dated_in_the_future(): void
    {
        $container = $this->container();
        $gateIn    = $this->move($container, 'in', '2026-09-07 11:41');

        $finding = $this->findings()->firstWhere('movement.id', $gateIn->id);

        $this->assertNotNull($finding);
        $this->assertSame(GateDataCheck::FUTURE_GATE_IN, $finding['check']);
    }

    /** As `TRHU4193252` is: in 14:43, out 13:09, same afternoon. */
    public function test_it_finds_a_departure_before_its_arrival_on_the_same_date(): void
    {
        $container = $this->container();
        $job       = $this->job();
        $this->move($container, 'in', '2026-09-01 14:43', $job);
        $gateOut = $this->move($container, 'out', '2026-09-01 13:09', $job);

        $finding = $this->findings()->firstWhere('movement.id', $gateOut->id);

        $this->assertNotNull($finding);
        $this->assertSame(GateDataCheck::OUT_BEFORE_IN, $finding['check']);
        $this->assertStringContainsString('14:43', $finding['detail']);
        $this->assertStringContainsString('13:09', $finding['detail']);
    }

    /** As `GESU6455892` is: a departure with nothing before it. */
    public function test_it_finds_a_departure_with_no_arrival_on_record(): void
    {
        $container = $this->container();
        $gateOut   = $this->move($container, 'out', '2026-07-14 10:50');

        $finding = $this->findings()->firstWhere('movement.id', $gateOut->id);

        $this->assertNotNull($finding);
        $this->assertSame(GateDataCheck::NO_GATE_IN, $finding['check']);
    }

    // ── What must NOT be a finding ──────────────────────────────────────────

    /** The ordinary case the whole gate-validation design protected. */
    public function test_a_correct_same_date_turnaround_is_not_a_finding(): void
    {
        $container = $this->container();
        $this->move($container, 'in', '2026-09-01 08:00');
        $this->move($container, 'out', '2026-09-01 17:00');

        $this->assertEmpty($this->findingsFor($container));
    }

    /**
     * A same-date pair recorded date-only stores both ends as `00:00:00` and
     * compares as exactly equal. The check uses the same `>=` rule the gate
     * validation does — if it did not, this screen would report problems the
     * gate would refuse to let anyone fix.
     */
    public function test_a_date_only_same_date_pair_is_not_a_finding(): void
    {
        $container = $this->container();
        $this->move($container, 'in', '2026-09-01 00:00:00');
        $this->move($container, 'out', '2026-09-01 00:00:00');

        $this->assertEmpty($this->findingsFor($container));
    }

    public function test_a_container_still_in_the_yard_is_not_a_finding(): void
    {
        $container = $this->container();
        $this->move($container, 'in', '2026-08-20 08:00');

        $this->assertEmpty($this->findingsFor($container));
    }

    public function test_an_ordinary_multi_day_visit_is_not_a_finding(): void
    {
        $container = $this->container();
        $this->move($container, 'in', '2026-08-01 08:00');
        $this->move($container, 'out', '2026-08-14 16:00');

        $this->assertEmpty($this->findingsFor($container));
    }

    /**
     * One finding per movement. A future-dated arrival is also, by arithmetic,
     * after its own departure — listing both would double the work without
     * saying anything new.
     */
    public function test_a_future_arrival_with_an_earlier_departure_reports_once(): void
    {
        $container = $this->container();
        $this->move($container, 'in', '2026-09-20 11:00');
        $this->move($container, 'out', '2026-08-28 11:23');

        $checks = $this->findingsFor($container)->pluck('check');

        $this->assertCount(1, $checks);
        $this->assertSame(GateDataCheck::FUTURE_GATE_IN, $checks->first());
    }

    // ── The screen ──────────────────────────────────────────────────────────

    public function test_the_screen_lists_open_findings(): void
    {
        $container = $this->container();
        $this->move($container, 'in', '2026-09-07 11:41');

        $response = $this->get(route('reports.gate-data-check'))->assertOk();

        $response->assertSee($container->container_no);
        $response->assertSee('Gate-in in the future');
        $this->assertCount(1, $response->viewData('open'));
    }

    public function test_the_screen_says_so_when_there_is_nothing_to_fix(): void
    {
        $this->get(route('reports.gate-data-check'))->assertOk()->assertSee('Nothing to fix');
    }

    public function test_the_customer_filter_narrows_the_list(): void
    {
        $mine   = $this->container();
        $theirs = Container::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
        ]);
        $this->move($mine, 'in', '2026-09-07 11:41');
        $this->move($theirs, 'in', '2026-09-08 11:41', null, $theirs->customer_id);

        $open = $this->get(route('reports.gate-data-check', ['customer_id' => $this->customer->id]))
            ->assertOk()->viewData('open');

        $this->assertCount(1, $open);
    }

    // ── Reviewing ───────────────────────────────────────────────────────────

    public function test_a_reviewed_finding_moves_off_the_open_list(): void
    {
        $container = $this->container();
        $gateOut   = $this->move($container, 'out', '2026-07-14 10:50');

        $this->post(route('reports.gate-data-check.review', $gateOut), [
            'check' => GateDataCheck::NO_GATE_IN,
            'note'  => 'Arrival never recorded, pre-dates the system.',
        ])->assertRedirect();

        $response = $this->get(route('reports.gate-data-check'))->assertOk();

        $this->assertCount(0, $response->viewData('open'));
        $this->assertCount(1, $response->viewData('reviewed'));
    }

    /**
     * The note is required, and this is why: without it the button is a way to
     * clear the list without looking at anything.
     */
    public function test_a_review_without_a_reason_is_refused(): void
    {
        $container = $this->container();
        $gateOut   = $this->move($container, 'out', '2026-07-14 10:50');

        $this->post(route('reports.gate-data-check.review', $gateOut), [
            'check' => GateDataCheck::NO_GATE_IN,
            'note'  => '',
        ])->assertSessionHasErrors('note');

        $this->assertSame(0, GateCheckReview::count());
    }

    /**
     * A note accepts one finding, not the row forever.
     *
     * Here the same movement changes from a reversed pair into a departure with
     * no arrival at all — a different problem, which the old note says nothing
     * about, so it comes back.
     */
    public function test_a_movement_that_develops_a_different_problem_surfaces_again(): void
    {
        $container = $this->container();
        $job       = $this->job();
        $gateIn    = $this->move($container, 'in', '2026-09-01 14:43', $job);
        $gateOut   = $this->move($container, 'out', '2026-09-01 13:09', $job);

        $this->post(route('reports.gate-data-check.review', $gateOut), [
            'check' => GateDataCheck::OUT_BEFORE_IN,
            'note'  => 'Checked against the paperwork; leaving as recorded.',
        ]);

        $this->assertCount(0, $this->get(route('reports.gate-data-check'))->viewData('open'));

        // The arrival is removed, so the departure now has none at all.
        $gateIn->delete();

        $open = $this->get(route('reports.gate-data-check'))->assertOk()->viewData('open');

        $this->assertCount(1, $open, 'The accepted note covered the old finding, not this one.');
        $this->assertSame(GateDataCheck::NO_GATE_IN, $open[0]['check']);
    }

    public function test_reopening_puts_a_finding_back(): void
    {
        $container = $this->container();
        $gateOut   = $this->move($container, 'out', '2026-07-14 10:50');

        $this->post(route('reports.gate-data-check.review', $gateOut), [
            'check' => GateDataCheck::NO_GATE_IN, 'note' => 'Nothing to correct here.',
        ]);
        $this->delete(route('reports.gate-data-check.unreview', $gateOut), [
            'check' => GateDataCheck::NO_GATE_IN,
        ])->assertRedirect();

        $this->assertCount(1, $this->get(route('reports.gate-data-check'))->viewData('open'));
    }

    // ── Authorization ───────────────────────────────────────────────────────

    public function test_the_screen_is_refused_without_the_permission(): void
    {
        $this->actingAsRole('gate_officer');

        $this->get(route('reports.gate-data-check'))->assertForbidden();
    }

    /**
     * Viewing and accepting are separate permissions: someone may need to see
     * what is wrong without being able to declare it acceptable.
     */
    public function test_viewing_does_not_confer_reviewing(): void
    {
        $container = $this->container();
        $gateOut   = $this->move($container, 'out', '2026-07-14 10:50');

        $this->actingAs($this->userWithPermissions(['gate-check.view']));

        $this->get(route('reports.gate-data-check'))->assertOk();
        $this->post(route('reports.gate-data-check.review', $gateOut), [
            'check' => GateDataCheck::NO_GATE_IN, 'note' => 'Trying without the permission.',
        ])->assertForbidden();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function findings()
    {
        return app(GateDataCheck::class)->findings();
    }

    private function findingsFor(Container $container)
    {
        return $this->findings()->filter(fn ($f) => $f['movement']->container_id === $container->id);
    }

    private function container(): Container
    {
        return Container::factory()->create(['customer_id' => $this->customer->id]);
    }

    private function job(): ?int
    {
        $type = \App\Models\YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)->firstOrFail();
        ['job_no' => $no, 'job_seq' => $seq] = \App\Models\YardJob::generateJobNo($type);

        return \App\Models\YardJob::create([
            'job_no' => $no, 'job_seq' => $seq,
            'job_type_id' => $type->id, 'job_type_code' => $type->job_type_code,
            'type_short_code' => $type->type_short_code,
            'customer_id' => $this->customer->id, 'status' => 'open',
            'started_at' => now(), 'created_by' => auth()->id(),
        ])->id;
    }

    private function move(
        Container $container, string $type, string $at,
        ?int $jobId = null, ?int $customerId = null,
    ): GateMovement {
        return GateMovement::create([
            'container_id'   => $container->id,
            'container_no'   => $container->container_no,
            'customer_id'    => $customerId ?? $this->customer->id,
            'yard_job_id'    => $jobId,
            'movement_type'  => $type,
            'size'           => '20',
            'container_type' => 'GP',
            'cargo_status'   => 'empty',
            'gate_in_time'   => $type === 'in' ? $at : null,
            'gate_out_time'  => $type === 'out' ? $at : null,
            'created_by'     => auth()->id(),
        ]);
    }

    /** A user holding exactly the named permissions, and nothing else. */
    private function userWithPermissions(array $names): User
    {
        $role = Role::create([
            'name'         => 'gate_check_tester',
            'display_name' => 'Gate Check Tester',
            'description'  => 'Test-only role.',
        ]);

        $role->permissions()->sync(Permission::whereIn('name', $names)->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->flushPermissionCache();

        return $user;
    }
}
