<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('approval_workflows')->insertOrIgnore([
            [
                'document_type'          => 'gate_pass_in',
                'step_order'             => 1,
                'step_key'               => 'ops_receive',
                'step_label'             => 'Operations / Receiving',
                'required_role'          => null,
                'auto_approve_on_create' => true,
                'is_active'              => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ],
            [
                'document_type'          => 'gate_pass_in',
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
                'document_type'          => 'gate_pass_in',
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
        DB::table('approval_workflows')
            ->where('document_type', 'gate_pass_in')
            ->delete();
    }
};
