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
 * Two disagreements matter.
 *
 * **The old line never read `gate_out_date`.** It was always
 * `gate_in_date->diffInDays(today())`, so a box whose departure had been
 * recorded while the master still said in-yard kept accruing days -- the drift
 * `containers:fix-gate-custody` repairs, displayed as a real stay.
 *
 * **And an unsigned `diffInDays()` is version-dependent.** Carbon 2 returns the
 * absolute distance, Carbon 3 a signed difference, so an arrival dated in the
 * future read as `15` on one and `-15` on the other. Both are wrong; the point
 * of `DaysInYard` is that it passes the flag explicitly and clamps, which is
 * why its own docblock calls this out. Nothing validates the master's two dates
 * against each other either -- `UpdateContainerRequest` has an `after_or_equal`,
 * but the gate writes bypass it -- so the shape is reachable, not theoretical.
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
     * An arrival dated in the future, which is the shape that separates the two
     * calculations here.
     *
     * The old line was `gate_in_date->diffInDays(today())`, with no sign flag,
     * so what an arrival fifteen days ahead produced depended on the Carbon
     * major version: `15` on 2 (absolute default) and `-15` on 3 (signed).
     * Either way the gate screen's picker offered "N days in yard" for a box
     * that has not arrived. `DaysInYard` passes the flag and clamps, so the
     * answer is 0 on both.
     */
    public function test_the_in_yard_search_clamps_an_arrival_dated_in_the_future(): void
    {
        $c = $this->container(['gate_in_date' => '2026-10-05']);

        $this->assertSame(0, $this->searchDays($c),
            'Not the fifteen-day distance to an arrival that has not happened.');
    }

    /**
     * A departure recorded while the master still says in-yard.
     *
     * The old line never read `gate_out_date` at all — it always counted to
     * today — so a box that left on the 6th kept accruing days. Exactly the
     * drift `containers:fix-gate-custody` repairs, displayed as if it were a
     * real stay.
     */
    public function test_the_in_yard_search_counts_to_the_departure_not_to_today(): void
    {
        $c = $this->container([
            'gate_in_date'  => '2026-09-01',
            'gate_out_date' => '2026-09-06',
        ]);

        $this->assertSame(5, $this->searchDays($c),
            'Five days to the departure, not nineteen to today.');
    }

    public function test_a_container_with_no_arrival_counts_nothing(): void
    {
        $c = $this->container(['gate_in_date' => null]);

        $this->assertNull($this->searchDays($c),
            'Null, not zero: there is no arrival to count from.');
    }

    // ── The container lookup JSON ───────────────────────────────────────────

    /** The badge on the gate-out form reads `days_in_yard` from this payload. */
    public function test_the_container_lookup_clamps_an_arrival_dated_in_the_future(): void
    {
        $c = $this->container(['gate_in_date' => '2026-10-05']);

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
            'container_no' => 'REVR0000001',
            'gate_in_date' => '2026-10-05',   // fifteen days from now
        ]);

        $row = $this->yardRowFor('REVR0000001');

        // Scoped to this container's own row, and anchored to the badge.
        // FeatureTestCase runs the full DatabaseSeeder, so the page carries
        // sample containers with day counts of their own -- a page-wide
        // assertion would be answering for them too. And "0d" / "15d" are two
        // and three characters, so a loose search would match inside a hex
        // colour anywhere in the markup.
        $this->assertMatchesRegularExpression('/rounded-pill[^>]*>\s*0d/', $row);
        $this->assertDoesNotMatchRegularExpression('/rounded-pill[^>]*>\s*15d/', $row,
            'The distance to an arrival that has not happened is not a stay.');
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** The one table row on the yard page that belongs to this container. */
    private function yardRowFor(string $containerNo): string
    {
        $html = $this->get(route('yard.index'))->assertOk()->getContent();

        foreach (preg_split('/<tr[\s>]/', $html) as $chunk) {
            if (str_contains($chunk, $containerNo)) {
                return $chunk;
            }
        }

        $this->fail("{$containerNo} was not listed on the yard page.");
    }

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
