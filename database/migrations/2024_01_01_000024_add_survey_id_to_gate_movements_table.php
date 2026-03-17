<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->foreignId('survey_id')
                  ->nullable()
                  ->after('container_id')
                  ->constrained('inquiries')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Inquiry::class, 'survey_id');
            $table->dropColumn('survey_id');
        });
    }
};
