<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJobType;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The gate-out screen shows the party the visit belongs to, not the one cached
 * on the container master.
 *
 * The customer was already taken from the visit when a gate-out is *saved* —
 * `ContainerCustodyService` settled that. What it did not change is what the
 * operator is shown beforehand: `lookup()` and `inYardSearch()` still read
 * `containers.customer_id`, so a container whose master had drifted displayed
 * one party while the system was about to record another.
 *
 * That is worse than either value being wrong on its own. A correctly-saved
 * gate-out looks like a bug, and the obvious "fix" — trusting the screen — is
 * the defect the custody service exists to prevent.
 *
 * Live case this is drawn from: SUDU1194023, gate-in and yard job both customer
 * 40, container master 24. Nothing in the application accounts for the master
 * holding 24, and the audit log records no change to it — which is exactly why
 * the gate must not depend on that field.
 */
class GateOutShowsVisitCustomerTest extends FeatureTestCase
{
    private Customer $gateInCustomer;   // the party that brought the box in
    private Customer $masterCustomer;   // a different party, cached on the master

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 09:00:00');
        $this->gateInCustomer = Customer::factory()->create(['name' => 'ABC LOGISTICS']);
        $this->masterCustomer = Customer::factory()->create(['name' => 'XYZ SHIPPING']);
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The lookup the form calls when a container is picked ────────────────

    public function test_the_lookup_returns_the_gate_in_customer_not_the_master(): void
    {
        $container = $this->driftedContainer('DRFT1234567');

        $body = $this->getJson(route('yard.container.lookup', 'DRFT1234567'))->json();

        $this->assertSame($this->gateInCustomer->id, $body['customer_id']);
        $this->assertSame('ABC LOGISTICS', $body['customer_name']);
        $this->assertNotSame($this->masterCustomer->id, $body['customer_id'],
            'The master holds XYZ; the gate must not read it.');

        // The master is left alone — this is a display rule, not a repair.
        $this->assertSame($this->masterCustomer->id, $container->fresh()->customer_id);
    }

    /** And says so, so a screen can point out that the two disagree. */
    public function test_the_lookup_flags_when_the_visit_and_the_master_disagree(): void
    {
        $this->driftedContainer('FLAG1234567');

        $this->assertTrue($this->getJson(route('yard.container.lookup', 'FLAG1234567'))->json('customer_from_visit'));
    }

    public function test_no_flag_when_they_agree(): void
    {
        $this->gateIn('SAME1234567');

        $this->assertFalse($this->getJson(route('yard.container.lookup', 'SAME1234567'))->json('customer_from_visit'));
    }

    // ── The typeahead that offers the container ─────────────────────────────

    public function test_the_search_shows_the_gate_in_customer(): void
    {
        $this->driftedContainer('SRCH1234567');

        $results = $this->getJson(route('yard.in-yard-search', ['q' => 'SRCH1234567']))->json('results');

        $this->assertCount(1, $results);
        $this->assertSame('ABC LOGISTICS', $results[0]['customer']);
    }

    /**
     * The typeahead fires per keystroke over up to 25 rows, so the batch
     * resolver exists to keep that from being two queries a row. Pinned because
     * the obvious refactor — calling the single-container method in a map — is
     * correct and quietly quadratic.
     */
    public function test_the_search_resolves_many_containers_without_a_query_per_row(): void
    {
        foreach (['BAT1234567A', 'BAT1234567B', 'BAT1234567C'] as $no) {
            $this->driftedContainer($no);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $results = $this->getJson(route('yard.in-yard-search', ['q' => 'BAT123456']))->json('results');
        $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertCount(3, $results);
        foreach ($results as $row) {
            $this->assertSame('ABC LOGISTICS', $row['customer']);
        }
        $this->assertLessThan(15, $queries, 'Resolving the visit customer should not scale with the row count.');
    }

    // ── What is saved still matches what is shown ───────────────────────────

    public function test_the_saved_gate_out_records_the_same_party_the_form_showed(): void
    {
        $this->driftedContainer('SAVE1234567');

        $shown = $this->getJson(route('yard.container.lookup', 'SAVE1234567'))->json('customer_id');

        $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'  => 'SAVE1234567',
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => 'RO-CUST-1',
            'gate_out_time' => '2026-09-09 08:00:00',
        ])->assertSessionHasNoErrors();

        $out = GateMovement::where('container_no', 'SAVE1234567')->where('movement_type', 'out')->firstOrFail();

        $this->assertSame($this->gateInCustomer->id, $out->customer_id);
        $this->assertSame($shown, $out->customer_id, 'Shown and saved must be the same party.');
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** Gated in under ABC, with the master since drifted to XYZ. */
    private function driftedContainer(string $no): Container
    {
        $this->gateIn($no);

        $container = Container::where('container_no', $no)->firstOrFail();

        // Written straight to the column: the master screen cannot do this any
        // more, and no in-app writer explains the live case either.
        Container::where('id', $container->id)->update(['customer_id' => $this->masterCustomer->id]);

        return $container->fresh();
    }

    private function gateIn(string $containerNo): void
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')->first();

        $eqt = EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => $containerNo,
            'equipment_type_id' => $eqt->id,
            'customer_id'       => $this->gateInCustomer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => '2026-09-04 20:35:00',
        ])->assertSessionHasNoErrors();
    }
}
