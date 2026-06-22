<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_configs', function (Blueprint $table) {
            // Common CC list for external sends of this category — every
            // customer-facing email in the category is copied to these addresses.
            // Stored as JSON array of email strings.
            $table->json('cc_emails')->nullable()->after('reply_to');
        });
    }

    public function down(): void
    {
        Schema::table('email_configs', function (Blueprint $table) {
            $table->dropColumn('cc_emails');
        });
    }
};
