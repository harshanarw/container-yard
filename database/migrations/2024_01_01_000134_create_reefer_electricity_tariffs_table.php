<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reefer_electricity_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('tariff_name', 100);
            $table->enum('billing_mode', ['hourly', 'daily']);
            $table->char('currency', 3)->default('LKR');
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('daily_rate',  10, 2)->nullable();
            $table->decimal('free_hours',  5, 2)->default(0);
            $table->unsignedInteger('free_days')->default(0);
            $table->decimal('minimum_charge', 10, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reefer_electricity_tariffs'); }
};
