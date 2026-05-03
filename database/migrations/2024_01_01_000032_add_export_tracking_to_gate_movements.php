<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->timestamp('codeco_exported_at')->nullable()->after('remarks');
            $table->timestamp('csv_exported_at')->nullable()->after('codeco_exported_at');
            $table->foreignId('codeco_exported_by')->nullable()->constrained('users')->nullOnDelete()->after('csv_exported_at');
            $table->foreignId('csv_exported_by')->nullable()->constrained('users')->nullOnDelete()->after('codeco_exported_by');
            $table->string('codeco_batch_ref', 50)->nullable()->after('csv_exported_by');
            $table->string('csv_batch_ref', 50)->nullable()->after('codeco_batch_ref');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('codeco_exported_by');
            $table->dropConstrainedForeignId('csv_exported_by');
            $table->dropColumn(['codeco_exported_at', 'csv_exported_at', 'codeco_batch_ref', 'csv_batch_ref']);
        });
    }
};
