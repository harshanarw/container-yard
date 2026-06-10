<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->string('container_ocr_image_path')->nullable()->after('csv_batch_ref');
            $table->string('plate_ocr_image_path')->nullable()->after('container_ocr_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropColumn(['container_ocr_image_path', 'plate_ocr_image_path']);
        });
    }
};
