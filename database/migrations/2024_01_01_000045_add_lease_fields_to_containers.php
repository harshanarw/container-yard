<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->string('lessor_name', 150)->nullable()->after('notes')
                ->comment('Name of company/entity the container is leased from');
            $table->string('lessor_code', 30)->nullable()->after('lessor_name')
                ->comment('Short code / BIC prefix of the lessor');
            $table->string('lease_reference', 100)->nullable()->after('lessor_code')
                ->comment('Lease contract or agreement reference number');
            $table->date('lease_start_date')->nullable()->after('lease_reference');
            $table->date('lease_end_date')->nullable()->after('lease_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn([
                'lessor_name', 'lessor_code', 'lease_reference',
                'lease_start_date', 'lease_end_date',
            ]);
        });
    }
};
