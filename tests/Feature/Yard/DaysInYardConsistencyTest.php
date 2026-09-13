<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The day count, computed one way.
 *
 * Four screens rolled their own `diffInDays()` against the container master
 * while the rest of the yard went through {@see \App\Support\DaysInYard}. They
 * agreed on the ordinary case and disagreed on every awkward one -- the worst
 * way for a figure to be wrong, because it looks right until somebody compares
 * two screens.
 *
 * The disagreement that matters: `diffInDays()` returns the *distance* between
 * two moments by default, so a container recorded as leaving before it arrived
 * came back as a confident positive number. Nothing validates the master's two
 * dates against each other -- `UpdateContainerRequest` has an
 * `after_or_equal`, but the gate writes bypass it -- so the shape is reachable.
 *
 * `Container::getDaysInYardAttribute()` was deleted in the same pass. Nothing
 * called it, it was not in `$appends`, and it disagreed with `DaysInYard`
 * twice: a bare `diffInDays()`, and a missing arrival defaulted to `now()` so
 * it returned 0 where the answer is "no arrival to count from".
 */
class DaysInYardConsistencyTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The accessor is gone ────────────────────────────────────────────────

    public function test_the_container_model_no_longer_defines_its_own_day_count(): void
    {
        $this->assertFalse(
            method_exists(Container::class, 'getDaysInYardAttribute'),
            'A fifth definition of a count DaysInYard owns is what the next person reaches for.',
        );
    }

    // ── The in-yard search JSON ─────────────────────────────────────────────

    public function test_the_in_yard_search_counts_from_arrival(): void
    {
        $c = $this->container(['gate_in_date' => '2026-09-05']);

        $this->assertSame(15, $this->searchDays($c));
    }

    /**
     * The defect, on the endpoint that feeds the gate screen's badge.
     *
     * Fifteen days apart, but the wrong way round. A bare `diffInDays()` gives
     * a confident 15; a plausible number on contradictory data is worse than a
     * negative one, because a negative at least looks like a fault.
     */
    public function test_the_in_yard_search_clamps_a_reversed_pair(): void
    {
        $c = $this->container([
            'gate_in_date'  => '2026-09-20',
            'gate_out_date' => '2026-09-05',
        ]);

        $this->assertSame(0, $this->searchDays($c), 'Not the fifteen-day distance.');
    }

    public function test_the_in_yard_search_counts_to_the_departure_once_it_has_left(): void
    {
        $c = $this->container([
            'gate_in_date'  => '2026-09-01',
            'gate_out_date' => '2026-09-11',
        ]);

        $this->assertSame(10, $this->searchDays($c), 'To the gate-out, not to today.');
    }

    public function test_a_container_with_no_arrival_counts_nothing(): void
    {
        $c = $this->container(['gate_in_date' => null]);

        $this->assertNull($this->searchDays($c),
            'Null, not zero: there is no arrival to count from.');
    }

    // ── The container lookup JSON ───────────────────────────────────────────

    /** The badge on the gate-out form reads `days_in_yard` from this payload. */
    public function test_the_container_lookup_clamps_a_reversed_pair(): void
    {
        $c = $this->container([
            'gate_in_date'  => '2026-09-20',
            'gate_out_date' => '2026-09-05',
        ]);

        $this->assertSame(0, $this->lookupDays($c));
    }

    public function test_the_container_lookup_counts_from_arrival(): void
    {
        $c = $this->container(['gate_in_date' => '2026-09-05']);

        $this->assertSame(15, $this->lookupDays($c));
    }

    // ── The yard list ───────────────────────────────────────────────────────

    /**
     * The badge is coloured by the number, so a phantom count is not just a
     * wrong figure -- it turns an ordinary box red.
     */
    public function test_the_yard_list_does_not_show_a_phantom_count(): void
    {
        $this->container([
            'container_no'  => 'REVR0000001',
            'gate_in_date'  => '2026-09-20',
            'gate_out_date' => '2026-09-05',
        ]);

        $html = $this->get(route('yard.index'))
            ->assertOk()
            ->assertSee('REVR0000001')
            ->getContent();

        // Anchored to the badge rather than searched for loose: "0d" and "15d"
        // are two and three characters and would match inside a hex colour
        // anywhere in the markup, so a bare assertSee could pass or fail for
        // reasons that have nothing to do with this count.
        $this->assertMatchesRegularExpression('/rounded-pill[^>]*>\s*0d/', $html);
        $this->assertDoesNotMatchRegularExpression('/rounded-pill[^>]*>\s*15d/', $html,
            'The fifteen-day distance between two reversed dates is not a stay.');
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function searchDays(Container $c): ?int
    {
        $results = $this->get(route('yard.in-yard-search', ['q' => $c->container_no]))
            ->assertOk()
            ->json('results');

        foreach ($results as $row) {
            if ($row['id'] === $c->container_no) {
                return $row['days'];
            }
        }

        $this->fail("{$c->container_no} was not in the search results.");
    }

    private function lookupDays(Container $c): ?int
    {
        return $this->get(route('yard.container-lookup', ['container_no' => $c->container_no]))
            ->assertOk()
            ->json('days_in_yard');
    }

    private function container(array $attributes = []): Container
    {
        return Container::factory()->create(array_merge([
            'customer_id' => $this->customer->id,
            'status'      => 'in_yard',
        ], $attributes));
    }
}
