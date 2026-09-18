<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Services\ContainerMrStatusService;
use App\Services\LessorOnHireService;
use App\Support\MrStatusCatalogue as Cat;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * What the shipping line sees while the yard has their container on hire.
 *
 * The box stays on the line's stock — it is still theirs and the yard still owes
 * it back — so removing it would have the line chasing containers the yard is
 * holding. Labelling it instead keeps the count honest and says why the storage
 * column is zero.
 *
 * Three states, because "is it with you" and "is it on your ground" are two
 * different questions and the line needs both answered:
 *
 *   In Yard              the ordinary case
 *   On Hire              the yard has taken it from the line
 *   On Hire - Rented Out and has since re-let it, so it is not even on site
 *
 * The status already exists for the *other* direction — the yard giving a box to
 * a customer — and `MrStatusContext` knew only about that one, so a leased-in
 * container showed no label at all.
 */
class LeaseInStatusLabelTest extends FeatureTestCase
{
    private Customer $line;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-18 10:00:00');

        // Signed in first: the arrival below stamps created_by, which is NOT
        // NULL on gate_movements, and auth()->id() is null until this runs.
        $this->actingAsSystemAdmin();

        $this->line      = Customer::factory()->create(['name' => 'Maersk Line']);
        $this->container = Container::factory()->create([
            'customer_id' => $this->line->id,
            'status'      => 'in_yard',
        ]);
        $this->arrive();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The three states ────────────────────────────────────────────────────

    public function test_a_container_with_no_lease_is_not_labelled_on_hire(): void
    {
        $this->assertNotContains($this->mrStatus(), [Cat::LEASED_IN, Cat::LEASED_IN_RENTED_OUT]);
    }

    public function test_a_leased_in_container_reads_on_hire(): void
    {
        $this->lease();

        $this->assertSame(Cat::LEASED_IN, $this->mrStatus());
        $this->assertSame('On hire', Cat::label(Cat::LEASED_IN));
    }

    /**
     * Leased in *and* re-let. "On hire" alone would not tell the line their
     * container has left the yard, which is the thing they would want to know.
     */
    public function test_a_leased_in_container_that_is_re_let_says_so(): void
    {
        $this->lease();
        $this->reLet();

        $this->assertSame(Cat::LEASED_IN_RENTED_OUT, $this->mrStatus());
        $this->assertSame('On hire - rented out', Cat::label(Cat::LEASED_IN_RENTED_OUT));
    }

    /**
     * The other direction on its own is unchanged: the yard gave a box to a
     * customer without having leased it in first.
     */
    public function test_a_plain_re_let_still_reads_on_hire(): void
    {
        $this->reLet();

        $this->assertSame(Cat::ON_HIRE, $this->mrStatus());
    }

    public function test_off_hiring_clears_the_label(): void
    {
        $hire = $this->lease();

        app(LessorOnHireService::class)->offHireInYard(
            $hire, ['off_hire_date' => '2026-10-20'], auth()->id(),
        );

        $this->assertNotContains($this->mrStatus(), [Cat::LEASED_IN, Cat::LEASED_IN_RENTED_OUT]);
    }

    // ── What the label implies ──────────────────────────────────────────────

    /** Both states are commitments, so neither is work the yard can schedule. */
    public function test_both_lease_states_are_committed(): void
    {
        $this->assertSame(Cat::GROUP_COMMITTED, Cat::group(Cat::LEASED_IN));
        $this->assertSame(Cat::GROUP_COMMITTED, Cat::group(Cat::LEASED_IN_RENTED_OUT));
    }

    /** A box the yard is paying rent on is not free to leave on somebody's booking. */
    public function test_a_leased_in_container_is_not_export_ready(): void
    {
        $this->lease();

        app(ContainerMrStatusService::class)->resolveAndSync($this->container->fresh());

        $this->assertFalse((bool) $this->container->fresh()->export_ready);
    }

    // ── It reaches the screens ──────────────────────────────────────────────

    /**
     * The status is a stored projection, so once it resolves the label appears
     * wherever `mr_status` is rendered — Container Inquiry, the yard list and
     * the stock reports — without each of them knowing about leases.
     */
    public function test_the_label_is_stored_on_the_container(): void
    {
        $this->lease();

        app(ContainerMrStatusService::class)->resolveAndSync($this->container->fresh());

        $this->assertSame(Cat::LEASED_IN, $this->container->fresh()->mr_status);
    }

    public function test_container_inquiry_shows_the_label(): void
    {
        $this->lease();
        app(ContainerMrStatusService::class)->resolveAndSync($this->container->fresh());

        $this->get(route('container-inquiry.show', $this->container->container_no))
            ->assertOk()
            ->assertSee('On hire');
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** Named `mrStatus`, not `status`: PHPUnit declares `status()` final. */
    private function mrStatus(): string
    {
        return app(ContainerMrStatusService::class)
            ->forContainer($this->container->fresh())
            ->code;
    }

    private function lease(): LessorOnHire
    {
        return app(LessorOnHireService::class)->onHireInYard(
            $this->container->fresh(),
            ['lessor_id' => $this->line->id, 'on_hire_date' => '2026-09-20'],
            auth()->id(),
        );
    }

    /** The yard puts the box out with a renting customer. */
    private function reLet(): ContainerHire
    {
        return ContainerHire::create([
            'container_id'         => $this->container->id,
            'original_customer_id' => $this->line->id,
            'hire_customer_id'     => Customer::factory()->create(['name' => 'ABC Traders'])->id,
            'on_hire_date'         => '2026-09-25',
            'status'               => 'active',
            'created_by'           => auth()->id(),
        ]);
    }

    private function arrive(): GateMovement
    {
        return GateMovement::create([
            'container_id'    => $this->container->id,
            'container_no'    => $this->container->container_no,
            'customer_id'     => $this->line->id,
            'movement_type'   => 'in',
            'size'            => '40',
            'container_type'  => 'HC',
            'condition'       => 'sound',
            'cargo_status'    => 'empty',
            'gate_in_time'    => '2026-09-01 08:00:00',
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);
    }
}
