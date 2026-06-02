<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_actions', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('step_label')
                  ->constrained('users')->nullOnDelete();
            $table->index('assigned_to', 'aa_assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('approval_actions', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropIndex('aa_assigned_to');
            $table->dropColumn('assigned_to');
        });
    }
};
