<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_approval_actions', function (Blueprint $table) {
            $table->string('approver_name')->nullable()->after('performed_by_email');
            $table->string('approver_designation')->nullable()->after('approver_name');
            $table->string('ip_address', 45)->nullable()->after('approver_designation');
            $table->text('user_agent')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_approval_actions', function (Blueprint $table) {
            $table->dropColumn(['approver_name', 'approver_designation', 'ip_address', 'user_agent']);
        });
    }
};
