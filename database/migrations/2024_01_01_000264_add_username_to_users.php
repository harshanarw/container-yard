<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Give every user a dedicated unique username as the login credential, so
 * staff who share a common mailbox (or have none) can still each have their
 * own account. Email becomes optional — kept unique for those who do have a
 * personal address (MySQL allows multiple NULLs on a unique index).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 50)->nullable()->unique()->after('name');
        });

        // Email is no longer the login key — make it optional.
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');

        // Backfill a unique username for existing rows: prefer the employee
        // reg-no, then the email local-part, else user{id}; de-duplicate.
        $taken = [];
        DB::table('users')->orderBy('id')->get(['id', 'email', 'employee_reg_no'])
            ->each(function ($u) use (&$taken) {
                $base = $u->employee_reg_no
                    ?: (Str::contains((string) $u->email, '@') ? Str::before($u->email, '@') : null)
                    ?: ('user' . $u->id);
                $base = Str::lower(preg_replace('/[^A-Za-z0-9._-]/', '', (string) $base)) ?: ('user' . $u->id);
                // Keep headroom under the VARCHAR(50) column for the dedup suffix.
                $base = substr($base, 0, 40);

                $username = $base;
                $n = 1;
                while (in_array($username, $taken, true)
                    || DB::table('users')->where('username', $username)->where('id', '!=', $u->id)->exists()) {
                    $username = $base . $n;
                    $n++;
                }
                $taken[] = $username;
                DB::table('users')->where('id', $u->id)->update(['username' => $username]);
            });

        // Now that every row has one, enforce NOT NULL.
        DB::statement('ALTER TABLE users MODIFY username VARCHAR(50) NOT NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['username']);
            $t->dropColumn('username');
        });
        // Email is left nullable on rollback — restoring NOT NULL could fail if
        // accounts without an email were created after this migration.
    }
};
