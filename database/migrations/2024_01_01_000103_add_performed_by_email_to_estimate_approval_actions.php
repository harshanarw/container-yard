<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_approval_actions', function (Blueprint $table) {
            $table->string('performed_by_email', 255)->nullable()->after('actioned_by');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_approval_actions', function (Blueprint $table) {
            $table->dropColumn('performed_by_email');
        });
    }
};
