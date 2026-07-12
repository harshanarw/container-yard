<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job Costing — Phase C: on-hire FROM a lessor (yard as lessee).
 *
 * The yard takes a container on hire from a shipping line / lessor. Each on-hire
 * gets its own YardJob so the on-hire→off-hire period has a dedicated P&L: the
 * lessor's fee is captured as an AP expense tagged to the job, and any revenue
 * from using the box is tagged to the same job. Off-hire completes the job.
 *
 * Revenue-first scope: per-diem accrual is out (a per_diem_rate is stored for a
 * later accrual phase); today the lessor cost is the actual supplier invoice /
 * voucher the operator tags to the job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessor_on_hires', function (Blueprint $t) {
            $t->id();
            $t->foreignId('yard_job_id')->nullable()->constrained('yard_jobs')->nullOnDelete();
            $t->foreignId('container_id')->constrained('containers')->restrictOnDelete();
            // The lessor (shipping line / leasing co) — modelled as a customer/contact.
            $t->foreignId('lessor_id')->constrained('customers')->restrictOnDelete();
            $t->foreignId('gate_movement_id')->nullable()->constrained('gate_movements')->nullOnDelete();

            $t->date('on_hire_date');
            $t->date('off_hire_date')->nullable();
            $t->string('hire_reference', 100)->nullable();
            $t->decimal('per_diem_rate', 15, 2)->nullable(); // reserved for future accrual
            $t->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $t->text('notes')->nullable();

            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['status', 'on_hire_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessor_on_hires');
    }
};
