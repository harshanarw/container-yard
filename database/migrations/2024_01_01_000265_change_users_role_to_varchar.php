<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The users.role column was a fixed ENUM, so it broke the moment a user was
 * assigned a role that wasn't baked into the enum (e.g. Billing Manager, or
 * any custom role created in Settings → Roles & Permissions). Roles are now
 * data-driven from the roles table, so store the role as a plain string and
 * let the application layer (Rule::in on the live role names) keep it valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role VARCHAR(100) NOT NULL DEFAULT 'gate_officer'");
    }

    public function down(): void
    {
        // Restore the previous enum. This will fail if any user holds a role
        // outside the list (e.g. billing_manager or a custom role).
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'system_administrator',
            'administrator',
            'yard_supervisor',
            'gate_officer',
            'inspector',
            'billing_clerk',
            'security_officer'
        ) DEFAULT 'gate_officer'");
    }
};
