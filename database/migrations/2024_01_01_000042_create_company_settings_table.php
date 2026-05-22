<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 200)->default('Container Yard Management');
            $table->string('tagline', 200)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('website', 200)->nullable();
            $table->string('vat_number', 100)->nullable();
            $table->string('tin_number', 100)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        DB::table('company_settings')->insert([
            'company_name' => 'Container Yard Management',
            'tagline'      => 'Container Yard Management System',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
