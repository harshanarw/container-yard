<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier master — the Accounts-Payable counterpart to the Customer master.
 * Trimmed of yard-specific fields (no tariffs, contracts, logos, self-FKs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('registration_no', 50)->nullable();
            $table->string('tin_number', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->default('Sri Lanka');
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->enum('currency', ['LKR', 'USD', 'SGD'])->default('LKR');
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->enum('payment_terms', ['cod', 'net15', 'net30', 'net45', 'net60'])->default('net30');
            $table->enum('status', ['active', 'pending', 'inactive'])->default('active');
            $table->boolean('tax_exempt')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
