<?php

namespace Tests\Feature\Yard;

use App\Models\CargoTransfer;
use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for cargo rental / container substitution ("cross-stuffing").
 *
 * A customer's laden box is gated in under CARGO_RENTAL_IN; the cargo is moved to
 * a yard-owned substitute box; the empty source box is gated out; and the
 * customer is put on storage (no free days) + reefer electricity (when the
 * substitute is refrigerated) — all under one job.
 */
class CargoTransferFlowTest extends FeatureTestCase
{
    /** Build a laden source box gated in under a CARGO_RENTAL_IN job. */
    private function seedSource(Customer $customer): array
    {
        $jobType = YardJobType::where('job_type_code', 'CARGO_RENTAL_IN')->firstOrFail();

        $source = Container::factory()->create([
            'customer_id'  => $customer->id,
            'status'       => 'in_yard',
            'cargo_status' => 'laden',
        ]);

        ['job_no' => $jobNo, 'job_seq' => $jobSeq] = YardJob::generateJobNo($jobType);
        $job = YardJob::create([
            'job_no' => $jobNo, 'job_seq' => $jobSeq,
            'job_type_id' => $jobType->id, 'job_type_code' => $jobType->job_type_code,
            'type_short_code' => $jobType->type_short_code, 'customer_id' => $customer->id,
            'status' => 'open', 'started_at' => now(), 'created_by' => auth()->id(),
        ]);

        $sourceIn = GateMovement::create([
            'container_id' => $source->id, 'container_no' => $source->container_no,
            'customer_id' => $customer->id, 'yard_job_id' => $job->id,
            'job_type_id' => $jobType->id, 'job_type_code' => $jobType->job_type_code,
            'movement_type' => 'in', 'size' => $source->size, 'container_type' => $source->type_code,
            'cargo_status' => 'laden', 'created_by' => auth()->id(),
        ]);

        YardStorage::create([
            'container_id' => $source->id, 'customer_id' => $customer->id,
            'gate_in_date' => now()->subDay()->toDateString(),
            'free_days' => 0, 'daily_rate' => 0, 'hire_type' => 'normal',
        ]);

        return [$source, $job, $sourceIn];
    }

    /** A yard-owned substitute box already in the yard, with its own gate-in. */
    private function seedSubstitute(string $typeCode = 'HC'): Container
    {
        $sub = Container::factory()->create([
            'status' => 'in_yard', 'type_code' => $typeCode, 'cargo_status' => 'empty',
        ]);
        GateMovement::create([
            'container_id' => $sub->id, 'container_no' => $sub->container_no,
            'customer_id' => $sub->customer_id, 'movement_type' => 'in',
            'size' => $sub->size, 'container_type' => $sub->type_code, 'created_by' => auth()->id(),
        ]);

        return $sub;
    }

    private function postTransfer(GateMovement $sourceIn, Container $substitute): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('yard.cargo-transfers.store'), [
            'source_gate_movement_id' => $sourceIn->id,
            'substitute_container_id' => $substitute->id,
            'substitute_source'       => 'yard_owned',
            'transfer_date'           => now()->toDateString(),
            'daily_rate'              => 1500,
            'cargo_description'       => '900 cartons of tea',
        ]);
    }

    public function test_cargo_transfer_to_reefer_substitute_swaps_boxes_and_stays_on_one_job(): void
    {
        $this->actingAsSystemAdmin();
        $customer = Customer::factory()->create();

        [$source, $job, $sourceIn] = $this->seedSource($customer);
        $substitute = $this->seedSubstitute('RF'); // reefer → electricity session

        $this->postTransfer($sourceIn, $substitute)->assertSessionHasNoErrors()->assertRedirect();

        $transfer = CargoTransfer::latest('id')->first();
        $this->assertNotNull($transfer, 'Cargo transfer was not recorded.');
        $this->assertSame('active', $transfer->status);
        $this->assertTrue((bool) $transfer->is_reefer);

        // Substitute box on customer storage, NO free days, under the same job.
        $this->assertDatabaseHas('yard_storage', [
            'id' => $transfer->substitute_yard_storage_id, 'container_id' => $substitute->id,
            'customer_id' => $customer->id, 'yard_job_id' => $job->id,
            'free_days' => 0, 'gate_out_date' => null,
        ]);
        $this->assertDatabaseHas('containers', [
            'id' => $substitute->id, 'status' => 'in_yard', 'cargo_status' => 'laden',
        ]);

        // Reefer electricity session opened for the substitute box, billed to customer.
        $this->assertNotNull($transfer->reefer_plug_session_id, 'No reefer plug session opened.');
        $this->assertDatabaseHas('reefer_plug_sessions', [
            'id' => $transfer->reefer_plug_session_id, 'container_id' => $substitute->id,
            'customer_id' => $customer->id, 'status' => 'pending',
        ]);

        // Source box gated out empty on the SAME job.
        $this->assertDatabaseHas('gate_movements', [
            'id' => $transfer->source_gate_out_movement_id, 'container_id' => $source->id,
            'movement_type' => 'out', 'yard_job_id' => $job->id, 'gate_out_purpose' => 'CARGO_RENTAL_OUT',
        ]);
        $this->assertDatabaseHas('containers', [
            'id' => $source->id, 'status' => 'released', 'cargo_status' => 'empty',
        ]);

        // Single job number: the job now spans the source's in + out movements.
        $this->assertSame(2, GateMovement::where('yard_job_id', $job->id)->count());
    }

    public function test_non_reefer_substitute_opens_storage_but_no_reefer_session(): void
    {
        $this->actingAsSystemAdmin();
        $customer = Customer::factory()->create();

        [, $job, $sourceIn] = $this->seedSource($customer);
        $substitute = $this->seedSubstitute('HC'); // dry box → no electricity

        $this->postTransfer($sourceIn, $substitute)->assertSessionHasNoErrors();

        $transfer = CargoTransfer::latest('id')->first();
        $this->assertFalse((bool) $transfer->is_reefer);
        $this->assertNull($transfer->reefer_plug_session_id, 'A dry box should not get a reefer session.');
        // Storage still opened, no free days, on the same job.
        $this->assertDatabaseHas('yard_storage', [
            'id' => $transfer->substitute_yard_storage_id, 'free_days' => 0, 'yard_job_id' => $job->id,
        ]);
    }

    public function test_completing_a_transfer_gates_substitute_out_closes_storage_reefer_and_marks_completed(): void
    {
        $this->actingAsSystemAdmin();
        $customer = Customer::factory()->create();

        [, $job, $sourceIn] = $this->seedSource($customer);
        $substitute = $this->seedSubstitute('RF'); // reefer → has a plug session to close

        $this->postTransfer($sourceIn, $substitute)->assertSessionHasNoErrors();
        $transfer = CargoTransfer::latest('id')->first();
        $this->assertSame('active', $transfer->status);

        // ── Complete: cargo collected, box gated out ──
        $this->post(route('yard.cargo-transfers.complete', $transfer), [
            'completion_date' => now()->addDays(5)->toDateString(),
            'release_box'     => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $transfer->refresh();
        $this->assertSame('completed', $transfer->status);
        $this->assertNotNull($transfer->completed_date);
        $this->assertNotNull($transfer->substitute_gate_out_movement_id, 'Substitute box was not gated out.');

        // Substitute storage closed (chargeable = every day; no free days).
        $this->assertDatabaseHas('yard_storage', [
            'id' => $transfer->substitute_yard_storage_id, 'chargeable_days' => 5,
        ]);
        $this->assertDatabaseMissing('yard_storage', [
            'id' => $transfer->substitute_yard_storage_id, 'gate_out_date' => null,
        ]);

        // Substitute box released; its out movement is on the same job.
        $this->assertDatabaseHas('containers', ['id' => $substitute->id, 'status' => 'released']);
        $this->assertDatabaseHas('gate_movements', [
            'id' => $transfer->substitute_gate_out_movement_id, 'yard_job_id' => $job->id, 'movement_type' => 'out',
        ]);

        // Reefer session closed.
        $this->assertDatabaseHas('reefer_plug_sessions', [
            'id' => $transfer->reefer_plug_session_id, 'status' => 'completed',
        ]);

        // Completing again is rejected.
        $this->from(route('yard.cargo-transfers.show', $transfer))
            ->post(route('yard.cargo-transfers.complete', $transfer), [
                'completion_date' => now()->addDays(6)->toDateString(),
                'release_box'     => 1,
            ])->assertSessionHas('error');
    }

    public function test_double_transfer_from_the_same_gate_in_is_rejected(): void
    {
        $this->actingAsSystemAdmin();
        $customer = Customer::factory()->create();

        [, , $sourceIn] = $this->seedSource($customer);

        // First transfer succeeds.
        $this->postTransfer($sourceIn, $this->seedSubstitute('HC'))->assertSessionHasNoErrors();
        $this->assertSame(1, CargoTransfer::count());

        // Second attempt from the same gate-in is blocked (flash error, no new row).
        $this->from(route('yard.cargo-transfers.index'))
            ->postTransfer($sourceIn, $this->seedSubstitute('HC'))
            ->assertSessionHas('error');

        $this->assertSame(1, CargoTransfer::count(), 'A second transfer must not be created.');
    }
}
