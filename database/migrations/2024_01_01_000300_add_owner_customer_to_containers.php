<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The container's owner, as a party rather than a string.
 *
 * A container has two parties and they are not the same thing:
 *
 *   Owner    — who owns the box. Changes rarely. A property of the asset.
 *   Customer — who brought it in and takes it out. Belongs to the visit, and
 *              differs from one visit to the next.
 *
 * Until now the owner was `owner_code` / `owner_name`, two free-text fields
 * with no link to anything, so "every box owned by X" was not a question the
 * system could answer. Meanwhile `containers.customer_id` was doing duty as
 * both — declared a master field, overwritten by every gate-in, and read at
 * gate-out as though it were the visit's customer.
 *
 * `owner_customer_id` is nullable on purpose: plenty of owners are shipping
 * lines the yard does not trade with and has no customer record for. Those keep
 * using the text fields, which stay as the fallback rather than being replaced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->foreignId('owner_customer_id')->nullable()->after('owner_name')
                  ->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_customer_id');
        });
    }
};
