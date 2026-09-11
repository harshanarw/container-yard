<?php

namespace Tests\Feature\Reports;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Services\ContainerInquiryService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The date window on the gate movement search.
 *
 * Both bounds used to test `gate_in_time`, so a search for August returned
 * containers that *arrived* in August. A box that arrived in June and left in
 * August -- whose gate-out is the very event being looked for -- was absent,
 * and nothing said so.
 *
 * The same mistake as the reefer bill excluding sessions that crossed a period
 * boundary, and as Inventory's date filters meaning "arrived between": a period
 * filter applied to one end of a two-ended thing.
 */
class GateMovementSearchWindowTest extends FeatureTestCase
{
    private Customer $customer;

    private const FROM = '2026-08-01';
    private const TO   = '2026-08-31';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 10:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The defect ──────────────────────────────────────────────────────────

    /** Arrived in June, left in August. The gate-out is the event being sought. */
    public function test_a_visit_that_departed_inside_the_window_is_found(): void
    {
        $c = $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');

        $this->assertTrue($this->found($c), 'Its departure falls in August.');
    }

    public function test_a_visit_that_arrived_inside_the_window_is_found(): void
    {
        $c = $this->visit('2026-08-05 08:00:00', '2026-10-02 09:00:00');

        $this->assertTrue($this->found($c));
    }

    public function test_a_visit_wholly_inside_the_window_is_found(): void
    {
        $c = $this->visit('2026-08-05 08:00:00', '2026-08-20 09:00:00');

        $this->assertTrue($this->found($c));
    }

    /**
     * Spanning the window without either gate falling in it is absent -- and
     * that is correct. This screen searches *movements*; nothing happened at
     * the gate in August. "Was it in the yard that month" is the stock report's
     * question, and it answers it.
     */
    public function test_a_visit_spanning_the_window_with_neither_gate_inside_is_absent(): void
    {
        $c = $this->visit('2026-06-01 08:00:00', '2026-10-01 09:00:00');

        $this->assertFalse($this->found($c),
            'Neither gate happened in August: this is a stock question, not a movement one.');
    }

    public function test_a_visit_wholly_outside_the_window_is_absent(): void
    {
        $c = $this->visit('2026-05-01 08:00:00', '2026-05-20 09:00:00');

        $this->assertFalse($this->found($c));
    }

    /** A box still in the yard has no departure, and its arrival still counts. */
    public function test_an_open_visit_is_found_by_its_arrival(): void
    {
        $c = $this->visit('2026-08-09 08:00:00', null);

        $this->assertTrue($this->found($c));
    }

    // ── The scopes ──────────────────────────────────────────────────────────

    /** `in` restores exactly what the screen did before. */
    public function test_arrived_only_excludes_a_visit_that_merely_departed(): void
    {
        $departed = $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');
        $arrived  = $this->visit('2026-08-05 08:00:00', null);

        $this->assertFalse($this->found($departed, 'in'));
        $this->assertTrue($this->found($arrived, 'in'));
    }

    public function test_departed_only_excludes_a_visit_that_merely_arrived(): void
    {
        $departed = $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');
        $arrived  = $this->visit('2026-08-05 08:00:00', null);

        $this->assertTrue($this->found($departed, 'out'));
        $this->assertFalse($this->found($arrived, 'out'));
    }

    /** An unknown scope is not a licence to return everything. */
    public function test_an_unrecognised_scope_falls_back_to_either(): void
    {
        $c = $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');

        $this->assertTrue($this->found($c, 'nonsense'));
    }

    // ── The departure has to belong to the arrival ──────────────────────────

    /**
     * A later visit's departure must not drag an earlier visit into the window.
     *
     * In and out in June, then in again in July and out in August. The June
     * visit is closed and gone; only the July one belongs in an August search.
     */
    public function test_a_later_departure_does_not_match_an_earlier_closed_visit(): void
    {
        $c = $this->visit('2026-06-01 08:00:00', '2026-06-20 09:00:00');
        $this->arrive($c, '2026-07-05 08:00:00');
        $this->depart($c, '2026-08-10 09:00:00');

        $rows = $this->rows();
        $mine = $rows->where('container_id', $c->id);

        $this->assertCount(1, $mine, 'One row: the visit that actually departed in August.');
        $this->assertSame('2026-07-05', $mine->first()->gate_in_time->toDateString());
    }

    /** Two genuine visits inside the window are two rows, each with its own gate-out. */
    public function test_two_visits_inside_the_window_give_two_rows(): void
    {
        $c = $this->visit('2026-08-02 08:00:00', '2026-08-08 09:00:00');
        $this->arrive($c, '2026-08-15 08:00:00');
        $this->depart($c, '2026-08-22 09:00:00');

        $this->assertCount(2, $this->rows()->where('container_id', $c->id),
            'Collapsing them would lose a gate-out.');
    }

    // ── The filters that already worked still work ──────────────────────────

    public function test_the_customer_filter_still_narrows(): void
    {
        $mine  = $this->visit('2026-08-05 08:00:00', null);
        $other = $this->visit('2026-08-06 08:00:00', null, Customer::factory()->create());

        $rows = $this->rows(['customer_id' => $this->customer->id]);

        $this->assertTrue($rows->contains('container_id', $mine->id));
        $this->assertFalse($rows->contains('container_id', $other->id));
    }

    public function test_a_search_with_no_dates_is_unaffected(): void
    {
        $c = $this->visit('2026-01-05 08:00:00', '2026-01-20 09:00:00');

        $rows = app(ContainerInquiryService::class)
            ->search(['customer_id' => $this->customer->id])
            ->getCollection();

        $this->assertTrue($rows->contains('container_id', $c->id));
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function rows(array $extra = [])
    {
        return app(ContainerInquiryService::class)
            ->search(array_merge([
                'date_from' => self::FROM,
                'date_to'   => self::TO,
            ], $extra), 100)
            ->getCollection();
    }

    private function found(Container $c, ?string $scope = null): bool
    {
        return $this->rows($scope ? ['movement_scope' => $scope] : [])
            ->contains('container_id', $c->id);
    }

    private function visit(string $in, ?string $out, ?Customer $customer = null): Container
    {
        $customer ??= $this->customer;

        $container = Container::factory()->create([
            'customer_id' => $customer->id,
            'status'      => $out ? 'released' : 'in_yard',
        ]);

        $this->arrive($container, $in, $customer);

        if ($out) {
            $this->depart($container, $out, $customer);
        }

        return $container;
    }

    private function arrive(Container $c, string $at, ?Customer $customer = null): GateMovement
    {
        return $this->movement($c, 'in', $at, $customer);
    }

    private function depart(Container $c, string $at, ?Customer $customer = null): GateMovement
    {
        return $this->movement($c, 'out', $at, $customer);
    }

    private function movement(Container $c, string $direction, string $at, ?Customer $customer = null): GateMovement
    {
        $customer ??= $this->customer;

        return GateMovement::create([
            'container_id'    => $c->id,
            'container_no'    => $c->container_no,
            'customer_id'     => $customer->id,
            'movement_type'   => $direction,
            'size'            => '40',
            'container_type'  => 'GP',
            'condition'       => 'sound',
            'cargo_status'    => 'laden',
            'gate_in_time'    => $direction === 'in'  ? $at : null,
            'gate_out_time'   => $direction === 'out' ? $at : null,
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);
    }
}
