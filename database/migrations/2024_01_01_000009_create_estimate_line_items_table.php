<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
            $table->string('component');
            $table->enum('repair_type', [
                'replace',
                'repair',
                'weld',
                'straighten',
                'clean_and_treat',
                'paint',
            ]);
            $table->decimal('qty', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('line_amount', 15, 2)->default(0)->comment('qty * unit_price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_line_items');
    }
};
