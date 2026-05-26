<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('title', 10)->nullable()->after('id');
            $table->string('first_name', 100)->nullable()->after('title');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('gender', 10)->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('national_id', 50)->nullable()->after('date_of_birth');
            $table->string('employee_reg_no', 50)->nullable()->after('national_id');
            $table->string('department', 100)->nullable()->after('employee_reg_no');
            $table->date('joined_date')->nullable()->after('department');
            $table->string('profile_photo')->nullable()->after('joined_date');
            $table->string('emergency_contact', 100)->nullable()->after('profile_photo');
            $table->string('emergency_phone', 20)->nullable()->after('emergency_contact');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'title', 'first_name', 'last_name', 'gender', 'date_of_birth', 'national_id',
                'employee_reg_no', 'department', 'joined_date', 'profile_photo',
                'emergency_contact', 'emergency_phone',
            ]);
        });
    }
};
