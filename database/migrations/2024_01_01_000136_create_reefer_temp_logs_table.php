<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reefer_temp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plug_session_id')->constrained('reefer_plug_sessions')->cascadeOnDelete();
            $table->dateTime('logged_at');
            $table->decimal('set_temperature',    5, 2)->nullable();
            $table->decimal('return_temperature', 5, 2)->nullable();
            $table->decimal('supply_temperature', 5, 2)->nullable();
            $table->decimal('humidity_pct', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reefer_temp_logs'); }
};
