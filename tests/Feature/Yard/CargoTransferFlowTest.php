<?php

namespace Tests\Feature\Yard;

use App\Models\CargoTransfer;
use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\ReeferPlugSession;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for cargo rental / container substitution ("cross-stuffing").
 *
 * A customer's laden box is gated in under CARGO_RENTAL_IN; the cargo is moved to
 * a yard-owned reefer substitute box; the empty source box is gated out; and the
 * customer is put on storage (no free days) + reefer electricity on the substitute
 * box — all under one job.
 */
class CargoTransferFlowTest extends FeatureTestCase
{
    public function test_cargo_transfer_swaps_boxes_bills_storage_and_stays_on_one_job(): void
    {
        $this->actingAsSystemAdmin();

        $customer  = Customer::factory()->create();
        $jobType   = YardJobType::where('job_type_code', 'CARGO_RENTAL_IN')->firstOrFail();

        // ── Source: customer's laden box, gated in under a CARGO_RENTAL_IN job ──
        $source = Container::factory()->create([
            'customer_id'  => $customer->id,
            'status'       => 'in_yard',
            'cargo_status' => 'laden',
        ]);

        ['job_no' => $jobNo, 'job_seq' => $jobSeq] = YardJob::generateJobNo($jobType);
        $job = YardJob::create([
            'job_no'          => $jobNo,
            'job_seq'         => $jobSeq,
            'job_type_id'     => $jobType->id,
            'job_type_code'   => $jobType->job_type_code,
            'type_short_code' => $jobType->type_short_code,
            'customer_id'     => $customer->id,
            'status'          => 'open',
            'started_at'      => now(),
            'created_by'      => auth()->id(),
        ]);

        $sourceIn = GateMovement::create([
            'container_id'   => $source->id,
            'container_no'   => $source->container_no,
            'customer_id'    => $customer->id,
            'yard_job_id'    => $job->id,
            'job_type_id'    => $jobType->id,
            'job_type_code'  => $jobType->job_type_code,
            'movement_type'  => 'in',
            'size'           => $source->size,
            'container_type' => $source->type_code,
            'cargo_status'   => 'laden',
            'created_by'     => auth()->id(),
        ]);

        YardStorage::create([
            'container_id' => $source->id,
            'customer_id'  => $customer->id,
            'gate_in_date' => now()->subDay()->toDateString(),
            'free_days'    => 0,
            'daily_rate'   => 0,
            'hire_type'    => 'normal',
        ]);

        // ── Substitute: a yard-owned reefer box already in the yard ──
        $substitute = Container::factory()->create([
            'status'       => 'in_yard',
            'type_code'    => 'RF',   // reefer → should get an electricity plug session
            'cargo_status' => 'empty',
        ]);
        GateMovement::create([   // the substitute's own gate-in (anchors the plug session)
            'container_id'   => $substitute->id,
            'container_no'   => $substitute->container_no,
            'customer_id'    => $substitute->customer_id,
            'movement_type'  => 'in',
            'size'           => $substitute->size,
            'container_type' => $substitute->type_code,
            'created_by'     => auth()->id(),
        ]);

        // ── Record the transfer ──
        $this->post(route('yard.cargo-transfers.store'), [
            'source_gate_movement_id' => $sourceIn->id,
            'substitute_container_id' => $substitute->id,
            'substitute_source'       => 'yard_owned',
            'transfer_date'           => now()->toDateString(),
            'daily_rate'              => 1500,
            'cargo_description'       => '900 cartons of tea',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $transfer = CargoTransfer::latest('id')->first();
        $this->assertNotNull($transfer, 'Cargo transfer was not recorded.');
        $this->assertSame('active', $transfer->status);
        $this->assertTrue((bool) $transfer->is_reefer);

        // Substitute box now on customer storage with NO free days, under the same job.
        $this->assertDatabaseHas('yard_storage', [
            'id'          => $transfer->substitute_yard_storage_id,
            'container_id' => $substitute->id,
            'customer_id' => $customer->id,
            'yard_job_id' => $job->id,
            'free_days'   => 0,
            'gate_out_date' => null,
        ]);

        // Substitute box holds the cargo.
        $this->assertDatabaseHas('containers', [
            'id' => $substitute->id, 'status' => 'in_yard', 'cargo_status' => 'laden',
        ]);

        // Reefer electricity session opened for the substitute box, billed to the customer.
        $this->assertNotNull($transfer->reefer_plug_session_id, 'No reefer plug session opened.');
        $this->assertDatabaseHas('reefer_plug_sessions', [
            'id'          => $transfer->reefer_plug_session_id,
            'container_id' => $substitute->id,
            'customer_id' => $customer->id,
            'status'      => 'pending',
        ]);

        // Source box gated out empty, on the SAME job.
        $this->assertNotNull($transfer->source_gate_out_movement_id, 'Source box was not gated out.');
        $this->assertDatabaseHas('gate_movements', [
            'id'               => $transfer->source_gate_out_movement_id,
            'container_id'     => $source->id,
            'movement_type'    => 'out',
            'yard_job_id'      => $job->id,
            'gate_out_purpose' => 'CARGO_RENTAL_OUT',
        ]);
        $this->assertDatabaseHas('containers', [
            'id' => $source->id, 'status' => 'released', 'cargo_status' => 'empty',
        ]);

        // Single job number: the job now spans the source's in + out movements.
        $this->assertSame(2, GateMovement::where('yard_job_id', $job->id)->count());
    }
}
