<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cargo rental / container substitution ("cross-stuffing").
 *
 * Records a swap where a customer's laden box is gated in, its cargo is
 * transferred into a yard-owned or on-hired substitute container, and the now
 * empty original box is gated back out (stopping the shipping line's detention
 * clock). The yard then charges the customer storage — with no free days —
 * on the substitute box, plus reefer electricity when it is refrigerated.
 *
 * One row ties the whole swap to a single YardJob: the source box + its in/out
 * movements, the substitute box, the storage period opened on it, and (for a
 * reefer) its plug session. Revenue-first scope: the on-hire COST side (lessor
 * per-diem) is out of scope here; container_hire_id is kept for a later phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_transfers', function (Blueprint $t) {
            $t->id();

            // The single job the whole swap is tracked under.
            $t->foreignId('yard_job_id')->nullable()->constrained('yard_jobs')->nullOnDelete();

            // The cargo owner being billed storage (usually the source box's customer).
            $t->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            // Source: the customer's / shipping line's laden box that came in.
            $t->foreignId('source_container_id')->constrained('containers')->restrictOnDelete();
            $t->foreignId('source_gate_movement_id')->nullable()->constrained('gate_movements')->nullOnDelete();
            $t->foreignId('source_gate_out_movement_id')->nullable()->constrained('gate_movements')->nullOnDelete();

            // Substitute: the yard-owned / on-hired box the cargo moves into.
            $t->foreignId('substitute_container_id')->constrained('containers')->restrictOnDelete();
            $t->enum('substitute_source', ['yard_owned', 'on_hired'])->default('yard_owned');
            // If on-hired, the yard's hire of that box (cost side — future phase).
            $t->foreignId('container_hire_id')->nullable()->constrained('container_hires')->nullOnDelete();

            // The billable records the swap opens on the substitute box.
            $t->foreignId('substitute_yard_storage_id')->nullable()->constrained('yard_storage')->nullOnDelete();
            $t->foreignId('reefer_plug_session_id')->nullable()->constrained('reefer_plug_sessions')->nullOnDelete();

            $t->boolean('is_reefer')->default(false);
            $t->date('transfer_date');
            $t->text('cargo_description')->nullable();
            $t->decimal('handling_charge', 15, 2)->default(0); // optional one-off transfer fee

            // active while cargo sits in the substitute box; completed on release.
            $t->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $t->text('notes')->nullable();

            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['status', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_transfers');
    }
};
