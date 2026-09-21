<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use App\Services\ContainerHireService;
use App\Services\LessorOnHireService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Who a gate movement is *for*, on every screen that says so.
 *
 * `customer_id` is the **visit** customer — whose stay the container is on —
 * and that is right, deliberate, and protected by
 * `containers:fix-gate-custody`. Eleven surfaces read it directly, which is
 * also right for every ordinary movement, because the party at the gate is the
 * same party.
 *
 * A rental release is where they come apart: the box goes out with a renting
 * customer on the re-let's job, while the visit stays the shipping line's. Each
 * of those surfaces named the line — the movement edit screen, Container
 * Inquiry, the container's own history, the daily movements report and its
 * exports — and the renter appeared on none of them.
 *
 * The rule is now stated once, on the model, and the screens ask for it:
 *
 *     holdingParty()  =  job's held_by, falling back to the visit customer
 *
 * `held_by` is null on everything except a hire, so the "unchanged" half of
 * these tests is the larger claim: eleven surfaces, all still rendering exactly
 * what they rendered before.
 */
class MovementPartySurfacesTest extends FeatureTestCase
{
    private Customer $line;
    private Customer $renter;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-20 10:00:00');
        $this->actingAsSystemAdmin();

        $this->line      = Customer::factory()->create(['name' => 'Maersk Line']);
        $this->renter    = Customer::factory()->create(['name' => 'ABC Traders']);
        $this->container = Container::factory()->create([
            'customer_id' => $this->line->id,
            'status'      => 'in_yard',
        ]);

        $this->arrive();
        $this->storage();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The rule ────────────────────────────────────────────────────────────

    public function test_the_holder_is_the_renter_on_a_rental_release(): void
    {
        $this->rentOut();

        $out = $this->departure();

        $this->assertSame($this->renter->id, $out->holdingParty()?->id);
        $this->assertTrue($out->heldByAnotherParty());
        $this->assertStringContainsString('ABC Traders', $out->partyLabel());
        $this->assertStringContainsString('Maersk Line', $out->partyLabel(),
            'The owner is still named, because the box is still theirs.');
    }

    /** The visit customer is untouched — it is a fact, not a mistake. */
    public function test_the_movement_still_records_the_visit_customer(): void
    {
        $this->rentOut();

        $this->assertSame($this->line->id, $this->departure()->customer_id);
    }

    public function test_an_ordinary_movement_reads_exactly_as_before(): void
    {
        $out = $this->departure(release: true);

        $this->assertSame($this->line->id, $out->holdingParty()?->id);
        $this->assertFalse($out->heldByAnotherParty());
        $this->assertSame('Maersk Line', $out->partyLabel());
    }

    // ── The job chain ───────────────────────────────────────────────────────

    public function test_the_departure_carries_all_three_jobs(): void
    {
        $this->rentOut();

        $chain = $this->departure()->jobChain();

        $this->assertSame(
            ['Gate-in job', 'On-hire (lease) job', 'Rent job'],
            array_column($chain, 'label'),
            'Outermost first: the line\'s stay, the yard\'s lease, the letting.',
        );
    }

    public function test_an_ordinary_movement_has_a_chain_of_one(): void
    {
        $this->assertCount(1, $this->departure(release: true)->jobChain());
    }

    /** `parent_job_id` admits a cycle, and a mis-set parent must not hang a screen. */
    public function test_a_parent_cycle_does_not_recurse_forever(): void
    {
        $this->rentOut();

        $job = $this->departure()->yardJob;
        $job->parentJob->update(['parent_job_id' => $job->id]);

        $this->assertLessThanOrEqual(5, count($this->departure()->fresh()->jobChain()));
    }

    // ── The screens ─────────────────────────────────────────────────────────

    public function test_the_movement_edit_screen_names_the_renter(): void
    {
        $this->rentOut();

        $this->get(route('yard.movements.edit', $this->departure()))
            ->assertOk()
            ->assertSee('ABC Traders')
            ->assertSee('Rent job');
    }

    public function test_container_inquiry_names_the_renter_on_the_departure(): void
    {
        $this->rentOut();

        $this->get(route('container-inquiry.show', $this->container->container_no))
            ->assertOk()
            ->assertSee('ABC Traders');
    }

    public function test_the_container_history_names_the_renter(): void
    {
        $this->rentOut();

        $this->get(route('containers.show', $this->container))
            ->assertOk()
            ->assertSee('ABC Traders');
    }

    public function test_the_gate_screen_names_the_renter(): void
    {
        $this->rentOut();

        $this->get(route('yard.gate'))->assertOk()->assertSee('ABC Traders');
    }

    // ── Daily Movements ─────────────────────────────────────────────────────

    /** Grouped by who was at the gate, so the lift sits with the party invoiced for it. */
    public function test_daily_movements_groups_the_release_under_the_renter(): void
    {
        $this->rentOut();

        $this->get(route('reports.daily-movements', ['export_status' => 'all']))
            ->assertOk()
            ->assertSee('ABC Traders')
            ->assertSee('On hire from');
    }

    public function test_the_daily_movements_filter_follows_the_grouping(): void
    {
        $this->rentOut();

        $this->get(route('reports.daily-movements', [
            'export_status' => 'all',
            'customer_id'   => $this->renter->id,
        ]))->assertOk()->assertSee($this->container->container_no);
    }

    /** The arrival is still the line's, so filtering to them still finds it. */
    public function test_the_line_still_sees_its_own_arrival(): void
    {
        $this->rentOut();

        $this->get(route('reports.daily-movements', [
            'export_status' => 'all',
            'customer_id'   => $this->line->id,
        ]))->assertOk()->assertSee($this->container->container_no);
    }

    // ── The exports ─────────────────────────────────────────────────────────

    /**
     * Appended, not repurposed. `Container Operator` keeps meaning the shipping
     * line — that is what the term means and what a reader expects — and the
     * party at the barrier gets a column of its own.
     */
    public function test_the_movements_csv_appends_the_party_at_gate(): void
    {
        $this->rentOut();

        $csv = $this->post(route('reports.daily-movements.export.csv'), [
            'movement_ids' => [$this->departure()->id],
        ])->streamedContent();

        $this->assertStringContainsString('Party At Gate', $csv);
        $this->assertStringContainsString('ABC Traders', $csv);
        $this->assertStringContainsString('Maersk Line', $csv, 'The operator column is unchanged.');
    }

    /**
     * The gate log is always a workbook, so this reads the xlsx rather than a
     * string — and reads *every* XML part in it, because openspout writes cell
     * text inline into `xl/worksheets/sheet1.xml` and leaves the shared-strings
     * part an empty stub. Asserting against the streamed bytes finds nothing:
     * the zip is deflated, so no cell text appears in it literally.
     */
    public function test_the_gate_log_export_names_the_renter_and_the_rent_job(): void
    {
        $this->rentOut();

        $text = $this->workbookText();

        $this->assertStringContainsString('Rented To', $text);
        $this->assertStringContainsString('Rent Job', $text);
        $this->assertStringContainsString('ABC Traders', $text);
        $this->assertStringContainsString('Maersk Line', $text,
            'The Customer column still names the visit customer.');
    }

    /** Every XML part of the gate-log workbook, as one string. */
    private function workbookText(): string
    {
        $response = $this->get(route('container-inquiry.gate-log', [
            'date_from' => '2026-03-01', 'date_to' => '2026-03-31',
        ]))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'gate-log-party-');
        file_put_contents($path, $response->streamedContent());

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'The workbook must be a readable xlsx.');

        $text = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, '.xml')) {
                $text .= $zip->getFromName($name);
            }
        }

        $zip->close();
        @unlink($path);

        return $text;
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** Lease the box in, let it out, and release it to the renter. */
    private function rentOut(): void
    {
        app(LessorOnHireService::class)->onHireInYard(
            $this->container->fresh(),
            ['lessor_id' => $this->line->id, 'on_hire_date' => '2026-03-10'],
            auth()->id(),
        );

        app(ContainerHireService::class)->onHire(
            $this->container->fresh(),
            ['on_hire_date' => '2026-03-12', 'hire_customer_id' => $this->renter->id],
            auth()->id(),
        );

        $this->release();
    }

    private function release(): void
    {
        $this->post(route('yard.gate.out'), [
            'container_no'  => $this->container->container_no,
            'vehicle_plate' => 'WXY-1234',
            'driver_name'   => 'D Perera',
            'driver_ic'     => '901234567V',
        ])->assertRedirect();
    }

    private function departure(bool $release = false): GateMovement
    {
        if ($release) {
            $this->release();
        }

        return GateMovement::where('container_id', $this->container->id)
            ->where('movement_type', 'out')->latest('gate_out_time')->firstOrFail();
    }

    private function arrive(): GateMovement
    {
        $eqt  = \App\Models\EquipmentType::firstOrFail();
        $type = YardJobType::where('job_type_code', 'LADEN_IN')->firstOrFail();

        $this->container->update([
            'equipment_type_id' => $eqt->id,
            'size'              => $eqt->size,
            'type_code'         => $eqt->type_code,
        ]);

        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($type);

        $job = YardJob::create([
            'job_no'          => $no,
            'job_seq'         => $seq,
            'job_type_id'     => $type->id,
            'job_type_code'   => $type->job_type_code,
            'type_short_code' => $type->type_short_code,
            'customer_id'     => $this->line->id,
            'status'          => 'open',
            'started_at'      => '2026-03-01 08:00:00',
            'created_by'      => auth()->id(),
        ]);

        return GateMovement::create([
            'container_id'    => $this->container->id,
            'container_no'    => $this->container->container_no,
            'customer_id'     => $this->line->id,
            'yard_job_id'     => $job->id,
            'movement_type'   => 'in',
            'size'            => $eqt->size,
            'container_type'  => $eqt->type_code,
            'condition'       => 'sound',
            'cargo_status'    => 'empty',
            'gate_in_time'    => '2026-03-01 08:00:00',
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);
    }

    private function storage(): YardStorage
    {
        return YardStorage::create([
            'container_id'  => $this->container->id,
            'customer_id'   => $this->line->id,
            'gate_in_date'  => '2026-03-01',
            'gate_out_date' => null,
            'free_days'     => 0,
            'daily_rate'    => 750,
            'hire_type'     => 'normal',
        ]);
    }
}
