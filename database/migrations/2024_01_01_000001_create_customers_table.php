<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->enum('type', [
                'shipping_line',
                'freight_forwarder',
                'depot_owner',
                'nvo_carrier',
                'leasing_company',
            ]);
            $table->string('registration_no', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->default('Malaysia');
            $table->string('contact_person')->nullable();
            $table->string('designation', 100)->nullable();
            $table->string('phone_office', 20)->nullable();
            $table->string('phone_mobile', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->enum('currency', ['LKR', 'USD', 'SGD'])->default('LKR');
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->enum('payment_terms', ['cod', 'net15', 'net30', 'net45', 'net60'])->default('net30');
            $table->decimal('rate_20gp', 10, 2)->default(0)->comment('Daily storage rate for 20ft GP');
            $table->decimal('rate_40gp', 10, 2)->default(0)->comment('Daily storage rate for 40ft GP');
            $table->decimal('rate_40hc', 10, 2)->default(0)->comment('Daily storage rate for 40ft HC');
            $table->unsignedTinyInteger('free_days')->default(7);
            $table->enum('status', ['active', 'pending', 'inactive'])->default('active');
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->boolean('email_notifications')->default(true);
            $table->boolean('auto_invoice')->default(false);
            $table->string('logo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
