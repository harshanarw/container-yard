<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('internal_notification_emails', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->index();
            $table->string('email');
            $table->string('label', 100)->nullable();
            $table->enum('address_type', ['to', 'cc'])->default('to');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('internal_notification_emails');
    }
};
