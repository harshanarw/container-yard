<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mr_tariff_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete()
                  ->comment('Owner/operator this tariff applies to; null = default/fallback tariff');
            $table->string('name', 100)->comment('Tariff name / reference label');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->char('currency', 3)->default('USD');
            // Equipment applicability — null means all sizes/types
            $table->set('applicable_sizes', ['20', '40', '45'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mr_tariff_headers');
    }
};
