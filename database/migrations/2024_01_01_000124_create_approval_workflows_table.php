<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50)
                  ->comment('gate_pass | estimate | invoice | survey | work_order …');
            $table->unsignedTinyInteger('step_order');
            $table->string('step_key', 60)
                  ->comment('Machine key, unique per document_type');
            $table->string('step_label', 100)
                  ->comment('Human-readable label shown in UI and on print');
            $table->string('required_role', 60)->nullable()
                  ->comment('User role required to action this step; null = any authenticated user');
            $table->boolean('auto_approve_on_create')->default(false)
                  ->comment('When true, step is auto-approved with the initiator on request creation');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['document_type', 'step_order'], 'aw_type_order');
            $table->unique(['document_type', 'step_key'],   'aw_type_key');
        });

        // Seed gate_pass workflow — three sequential steps
        DB::table('approval_workflows')->insert([
            [
                'document_type'          => 'gate_pass',
                'step_order'             => 1,
                'step_key'               => 'ops_issue',
                'step_label'             => 'Operations / Issuance',
                'required_role'          => null,
                'auto_approve_on_create' => true,
                'is_active'              => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ],
            [
                'document_type'          => 'gate_pass',
                'step_order'             => 2,
                'step_key'               => 'supervisor_approval',
                'step_label'             => 'Supervisor Approval',
                'required_role'          => 'yard_supervisor',
                'auto_approve_on_create' => false,
                'is_active'              => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ],
            [
                'document_type'          => 'gate_pass',
                'step_order'             => 3,
                'step_key'               => 'gate_confirmation',
                'step_label'             => 'Gate Officer Confirmation',
                'required_role'          => 'gate_officer',
                'auto_approve_on_create' => false,
                'is_active'              => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflows');
    }
};
