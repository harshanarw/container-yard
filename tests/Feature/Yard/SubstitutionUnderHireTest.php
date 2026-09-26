<?php

namespace Tests\Feature\Yard;

use App\Models\CargoTransfer;
use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use App\Services\LessorOnHireService;
use App\Support\MrStatusCatalogue as Cat;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Substitution using a container the yard holds on hire.
 *
 * The requirement's third relationship: a customer's laden box is swapped into
 * a substitute, and the substitute is one the yard is paying a shipping line
 * for. The yard bills the cargo customer storage on it and pays the line rent
 * for it, and the margin is the difference.
 *
 * `cargo_transfers.substitute_source` has had an `on_hired` value since the
 * table was written, and 000270 added `container_hire_id` beside it "for a
 * later phase". Nothing ever wrote that column — and it points at
 * `ContainerHire`, the yard as *lessor*, which is the opposite direction from a
 * box the yard holds on hire. So the enum was whatever the operator picked on
 * the form, with nothing to check it against.
 *
 * It is derived now, from the lease itself.
 */
class SubstitutionUnderHireTest extends FeatureTestCase
{
    private Customer $cargoCustomer;
    private Customer $line;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-04-10 10:00:00');
        $this->actingAsSystemAdmin();

        $this->cargoCustomer = Customer::factory()->create(['name' => 'Ceylon Tea Exports']);
        $this->line          = Customer::factory()->create(['name' => 'Maersk Line']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The source is derived, not declared ─────────────────────────────────

    public function test_a_leased_substitute_is_recorded_as_on_hired(): void
    {
        [$sourceIn, $substitute] = $this->seedBoxes(lease: true);

        $this->transfer($sourceIn, $substitute)->assertRedirect();

        $transfer = CargoTransfer::latest('id')->firstOrFail();

        $this->assertSame(CargoTransfer::SOURCE_ON_HIRED, $transfer->substitute_source);
        $this->assertTrue($transfer->isOnHired());
    }

    /** And the lease itself is linked, which is what makes the margin readable. */
    public function test_the_lease_is_linked_to_the_transfer(): void
    {
        [$sourceIn, $substitute] = $this->seedBoxes(lease: true);
        $lease = LessorOnHire::where('container_id', $substitute->id)->firstOrFail();

        $this->transfer($sourceIn, $substitute);

        $this->assertSame($lease->id, CargoTransfer::latest('id')->firstOrFail()->lessor_on_hire_id);
    }

    public function test_a_yard_owned_substitute_is_recorded_as_yard_owned(): void
    {
        [$sourceIn, $substitute] = $this->seedBoxes(lease: false);

        $this->transfer($sourceIn, $substitute);

        $transfer = CargoTransfer::latest('id')->firstOrFail();

        $this->assertSame(CargoTransfer::SOURCE_YARD_OWNED, $transfer->substitute_source);
        $this->assertNull($transfer->lessor_on_hire_id);
    }

    /**
     * The form used to decide this. An operator ticking the wrong box would
     * have hidden the cost side of the substitution's margin, and nothing
     * would have contradicted them.
     */
    public function test_the_form_cannot_contradict_the_data(): void
    {
        [$sourceIn, $substitute] = $this->seedBoxes(lease: true);

        $this->transfer($sourceIn, $substitute, ['substitute_source' => 'yard_owned']);

        $this->assertSame(
            CargoTransfer::SOURCE_ON_HIRED,
            CargoTransfer::latest('id')->firstOrFail()->substitute_source,
            'The yard is paying rent on this box whatever the form said.',
        );
    }

    // ── Storage: two rows, two sides of the margin ──────────────────────────

    /**
     * The lease's zero-rated row stays open beside the cargo row, and that is
     * correct rather than an oversight: the yard goes on paying the line while
     * the box holds somebody's cargo. Billing reads only `normal`/`resumed`, so
     * the lease row reaches no invoice.
     */
    public function test_the_lease_row_survives_and_the_cargo_row_opens_beside_it(): void
    {
        [$sourceIn, $substitute] = $this->seedBoxes(lease: true);

        $this->transfer($sourceIn, $substitute);

        $rows = YardStorage::where('container_id', $substitute->id)->whereNull('gate_out_date')->get();

        $this->assertCount(2, $rows);
        $this->assertTrue($rows->contains(fn ($r) => $r->hire_type === 'lease_in' && (float) $r->daily_rate === 0.0));
        $this->assertTrue($rows->contains(fn ($r) => $r->hire_type === 'normal'
            && (int) $r->customer_id === $this->cargoCustomer->id));
    }

    public function test_completing_closes_the_cargo_row_and_not_the_lease(): void
    {
        [$sourceIn, $substitute] = $this->seedBoxes(lease: true);
        $this->transfer($sourceIn, $substitute);

        Carbon::setTestNow('2026-04-20 10:00:00');
        $this->complete();

        $this->assertNotNull(
            YardStorage::where('container_id', $substitute->id)->where('hire_type', 'normal')->value('gate_out_date'),
        );
        $this->assertNull(
            YardStorage::where('container_id', $substitute->id)->where('hire_type', 'lease_in')->value('gate_out_date'),
            'The lease runs until it is off-hired, not until the cargo is collected.',
        );
    }

    // ── The box leaves, the lease does not ──────────────────────────────────

    /**
     * The per-diem does not stop because the cargo was collected, and nobody
     * watching the gate would think to check. Said at the moment the box goes.
     */
    public function test_releasing_a_leased_box_warns_that_the_lease_is_still_running(): void
    {
        [$sourceIn, $substitute] = $this->seedBoxes(lease: true);
        $this->transfer($sourceIn, $substitute);

        Carbon::setTestNow('2026-04-20 10:00:00');

        $this->complete()->assertSessionHas('warning');
    }

    public function test_a_yard_owned_release_says_nothing_about_a_lease(): void
    {
        [$sourceIn, $substitute] = $this->seedBoxes(lease: false);
        $this->transfer($sourceIn, $substitute);

        Carbon::setTestNow('2026-04-20 10:00:00');

        $this->complete()->assertSessionMissing('warning');
    }

    /** Off the ground, still the yard's responsibility — as the stock reports say. */
    public function test_the_released_box_still_reads_on_hire(): void
    {
        [$sourceIn, $substitute] = $this->seedBoxes(lease: true);
        $this->transfer($sourceIn, $substitute);

        Carbon::setTestNow('2026-04-20 10:00:00');
        $this->complete();

        app(\App\Services\ContainerMrStatusService::class)->resolveAndSync($substitute->fresh());

        $this->assertSame(Cat::LEASED_IN, $substitute->fresh()->mr_status);
        $this->assertSame('released', $substitute->fresh()->status);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** @return array{0: GateMovement, 1: Container} */
    private function seedBoxes(bool $lease): array
    {
        $jobType = YardJobType::where('job_type_code', 'CARGO_RENTAL_IN')->firstOrFail();

        $source = Container::factory()->create([
            'customer_id'  => $this->cargoCustomer->id,
            'status'       => 'in_yard',
            'cargo_status' => 'laden',
        ]);

        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($jobType);

        $job = YardJob::create([
            'job_no' => $no, 'job_seq' => $seq,
            'job_type_id' => $jobType->id, 'job_type_code' => $jobType->job_type_code,
            'type_short_code' => $jobType->type_short_code,
            'customer_id' => $this->cargoCustomer->id,
            'status' => 'open', 'started_at' => now(), 'created_by' => auth()->id(),
        ]);

        $sourceIn = GateMovement::create([
            'container_id' => $source->id, 'container_no' => $source->container_no,
            'customer_id' => $this->cargoCustomer->id, 'yard_job_id' => $job->id,
            'job_type_id' => $jobType->id, 'job_type_code' => $jobType->job_type_code,
            'movement_type' => 'in', 'size' => $source->size, 'container_type' => $source->type_code,
            'cargo_status' => 'laden', 'gate_in_time' => now()->subDay(), 'created_by' => auth()->id(),
        ]);

        YardStorage::create([
            'container_id' => $source->id, 'customer_id' => $this->cargoCustomer->id,
            'gate_in_date' => now()->subDay()->toDateString(),
            'free_days' => 0, 'daily_rate' => 0, 'hire_type' => 'normal',
        ]);

        $substitute = Container::factory()->create([
            'customer_id' => $this->line->id, 'status' => 'in_yard', 'cargo_status' => 'empty',
        ]);

        GateMovement::create([
            'container_id' => $substitute->id, 'container_no' => $substitute->container_no,
            'customer_id' => $this->line->id, 'movement_type' => 'in',
            'size' => $substitute->size, 'container_type' => $substitute->type_code,
            'gate_in_time' => now()->subDays(5), 'created_by' => auth()->id(),
        ]);

        YardStorage::create([
            'container_id' => $substitute->id, 'customer_id' => $this->line->id,
            'gate_in_date' => now()->subDays(5)->toDateString(),
            'free_days' => 0, 'daily_rate' => 500, 'hire_type' => 'normal',
        ]);

        if ($lease) {
            app(LessorOnHireService::class)->onHireInYard(
                $substitute->fresh(),
                ['lessor_id' => $this->line->id, 'on_hire_date' => now()->subDays(2)->toDateString()],
                auth()->id(),
            );
        }

        return [$sourceIn, $substitute->fresh()];
    }

    private function transfer(GateMovement $sourceIn, Container $substitute, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('yard.cargo-transfers.store'), array_merge([
            'source_gate_movement_id' => $sourceIn->id,
            'substitute_container_id' => $substitute->id,
            'substitute_source'       => 'yard_owned',
            'transfer_date'           => now()->toDateString(),
            'daily_rate'              => 1500,
            'cargo_description'       => '900 cartons of tea',
        ], $extra));
    }

    private function complete(): \Illuminate\Testing\TestResponse
    {
        return $this->post(
            route('yard.cargo-transfers.complete', CargoTransfer::latest('id')->firstOrFail()),
            ['completion_date' => now()->toDateString(), 'release_box' => 1],
        );
    }
}
