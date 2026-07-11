<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A short, unguessable code for each gate movement so the driver gate pass can
 * be shared over WhatsApp as a tidy branded link (/g/{code}) instead of the
 * long signed URL. Backfilled for existing rows; new movements get one via the
 * model's creating hook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->char('share_code', 12)->nullable()->unique()->after('id');
        });

        $taken = [];
        DB::table('gate_movements')->select('id')->orderBy('id')->get()->each(function ($row) use (&$taken) {
            do {
                $code = Str::lower(Str::random(12));
            } while (in_array($code, $taken, true) || DB::table('gate_movements')->where('share_code', $code)->exists());

            $taken[] = $code;
            DB::table('gate_movements')->where('id', $row->id)->update(['share_code' => $code]);
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropUnique(['share_code']);
            $table->dropColumn('share_code');
        });
    }
};
