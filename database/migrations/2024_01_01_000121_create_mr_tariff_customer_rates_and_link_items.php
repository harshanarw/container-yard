<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mr_tariff_customer_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('rate_code', 10);
            $table->decimal('rate_per_hour', 8, 2);
            $table->timestamps();

            $table->unique(['customer_id', 'rate_code']);

            $table->foreign('customer_id')
                  ->references('id')->on('customers')
                  ->cascadeOnDelete();
        });

        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->unsignedBigInteger('mr_tariff_item_id')
                  ->nullable()
                  ->after('mr_tariff_rule_id');

            $table->foreign('mr_tariff_item_id')
                  ->references('id')->on('mr_tariff_items')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->dropForeign(['mr_tariff_item_id']);
            $table->dropColumn('mr_tariff_item_id');
        });

        Schema::dropIfExists('mr_tariff_customer_rates');
    }
};
