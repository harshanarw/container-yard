<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The container type code is stored as a hardcoded ENUM
 * ('GP','HC','RF','OT','FR','TK') on several tables, but equipment types are
 * user-defined via the Equipment Type master (e.g. 'RH' — Reefer High Cube).
 * Gating in / quoting any container whose type code is outside that fixed list
 * fails with "Data truncated for column" under MySQL strict mode.
 *
 * Convert these columns to VARCHAR so new equipment type codes never break
 * inserts again. Only MySQL needs the change — on SQLite these enums are
 * already stored as TEXT and accept any value.
 */
return new class extends Migration
{
    /** table => column */
    private array $columns = [
        'containers'      => 'type_code',
        'inquiries'       => 'type_code',
        'gate_movements'  => 'container_type',
        'estimates'       => 'type_code',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columns as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(8) NOT NULL");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Restore the original enum. Rows with codes outside the list (e.g. 'RH')
        // would block this, so guard each table.
        foreach ($this->columns as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` ENUM('GP','HC','RF','OT','FR','TK') NOT NULL");
            }
        }
    }
};
