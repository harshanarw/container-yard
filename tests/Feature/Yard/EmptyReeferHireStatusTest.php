<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJobType;
use App\Services\LessorOnHireService;
use App\Support\MrStatusCatalogue as Cat;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * An empty reefer taken on hire, and the two things that made it read
 * "PTI due" on the Container Hires screen.
 *
 * A PTI tests refrigeration that is about to be used. An empty reefer sitting
 * in the yard with the machinery off is a Non-Operating Reefer, and demanding
 * one of it asks the yard to service equipment nobody will run.
 *
 * Both ends of the gate agreed about that in words and disagreed in code. The
 * controller defaults an empty reefer to NOR *when the field is absent*; the
 * form hard-coded `operating` as the checked radio whatever the cargo status
 * was, so the field was never absent and the server's rule never ran. Only the
 * browser corrected it, which means any path that did not fire that sync
 * recorded an empty box as running.
 *
 * The second defect made it stick. `LessorOnHire` was missing from the M&R
 * projection observer, so taking a container on hire from its line recomputed
 * nothing: the container kept whatever the gate-in had written, and the "On
 * hire" label the ladder can produce never appeared at all.
 */
class EmptyReeferHireStatusTest extends FeatureTestCase
{
    private Customer $line;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->actingAsSystemAdmin();

        $this->line = Customer::factory()->create(['name' => 'Maersk Line']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The gate ────────────────────────────────────────────────────────────

    /** The form's default now matches the rule the controller already had. */
    public function test_the_gate_form_offers_nor_for_an_empty_reefer(): void
    {
        $html = $this->get(route('yard.gate'))->assertOk()->getContent();

        $nor = strpos($html, 'id="reeferModeNor"');
        $this->assertNotFalse($nor);

        // The checked attribute sits on the NOR radio, not the operating one.
        $this->assertStringContainsString(
            'checked',
            substr($html, $nor, 200),
            'An empty reefer is the default cargo status, so NOR is the default machinery state.',
        );
    }

    public function test_an_empty_reefer_gated_in_is_recorded_as_non_operating(): void
    {
        $container = $this->arriveEmptyReefer();

        $this->assertSame('non_operating', GateMovement::where('container_id', $container->id)
            ->where('movement_type', 'in')->value('reefer_mode'));
    }

    /**
     * The symptom. A box nobody is going to plug in must not sit on the board
     * demanding an inspection of machinery nobody is going to switch on.
     */
    public function test_an_empty_reefer_does_not_read_pti_due(): void
    {
        $container = $this->arriveEmptyReefer();

        $this->assertNotSame(Cat::PTI_DUE, $container->fresh()->mr_status);
    }

    /** An empty reefer can legitimately be running — a feeder move, pre-cooling. */
    public function test_an_empty_reefer_marked_operating_still_wants_a_pti(): void
    {
        $container = $this->arriveEmptyReefer(['reefer_mode' => 'operating']);

        $this->assertSame(Cat::PTI_DUE, $container->fresh()->mr_status);
    }

    // ── The lease ───────────────────────────────────────────────────────────

    /**
     * Taking a box on hire changes what it is committed to, so the board has to
     * move. `LessorOnHire` was not observed, so it did not.
     */
    public function test_taking_a_container_on_hire_updates_its_status(): void
    {
        $container = $this->arriveEmptyReefer();

        app(LessorOnHireService::class)->onHireInYard(
            $container->fresh(),
            ['lessor_id' => $this->line->id, 'on_hire_date' => '2026-09-21'],
            auth()->id(),
        );

        $this->assertSame(Cat::LEASED_IN, $container->fresh()->mr_status,
            'Without the observer this kept whatever the gate-in wrote.');
    }

    public function test_off_hiring_moves_it_back(): void
    {
        $container = $this->arriveEmptyReefer();

        $lease = app(LessorOnHireService::class)->onHireInYard(
            $container->fresh(),
            ['lessor_id' => $this->line->id, 'on_hire_date' => '2026-09-21'],
            auth()->id(),
        );

        app(LessorOnHireService::class)->offHireInYard(
            $lease, ['off_hire_date' => '2026-10-20'], auth()->id(),
        );

        $this->assertNotSame(Cat::LEASED_IN, $container->fresh()->mr_status);
    }

    /** What the operator actually reads on the screen that prompted this. */
    public function test_the_container_hires_screen_shows_the_hire_not_a_pti(): void
    {
        $container = $this->arriveEmptyReefer();

        $this->get(route('yard.hires.create'))
            ->assertOk()
            ->assertDontSee(Cat::label(Cat::PTI_DUE));

        $this->assertNotNull($container->fresh()->mr_status);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** Through the real gate form, because the defect was in what it posts. */
    private function arriveEmptyReefer(array $extra = []): Container
    {
        $reefer = EquipmentType::where('type_code', 'RF')->firstOrFail();
        $type   = YardJobType::where('job_type_code', 'EMPTY_RETURN')->firstOrFail();

        $this->post(route('yard.gate.in'), array_merge([
            'job_type_id'       => $type->id,
            'return_reason'     => 'agent_return',
            'container_no'      => 'RFER0000001',
            'equipment_type_id' => $reefer->id,
            'customer_id'       => $this->line->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'WXY-1234',
        ], $extra))->assertRedirect();

        return Container::where('container_no', 'RFER0000001')->firstOrFail();
    }
}
