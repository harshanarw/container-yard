<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Change checklist_item from a fixed ENUM to a free VARCHAR so that
     * items managed through the Checklist Master can be added/removed
     * without requiring a schema migration each time.
     */
    public function up(): void
    {
        // Drop unique constraint, widen column, re-add constraint
        DB::statement('ALTER TABLE inquiry_checklists DROP INDEX inquiry_checklists_inquiry_id_checklist_item_unique');
        DB::statement('ALTER TABLE inquiry_checklists MODIFY checklist_item VARCHAR(100) NOT NULL');
        DB::statement('ALTER TABLE inquiry_checklists ADD UNIQUE KEY inquiry_checklists_inquiry_id_checklist_item_unique (inquiry_id, checklist_item)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inquiry_checklists DROP INDEX inquiry_checklists_inquiry_id_checklist_item_unique');
        DB::statement("ALTER TABLE inquiry_checklists MODIFY checklist_item ENUM(
            'exterior_panels_inspected','floor_board_condition_checked',
            'door_mechanism_tested','door_seals_gaskets_checked',
            'roof_integrity_verified','corner_castings_inspected',
            'base_rails_cross_members','forklift_pockets_checked',
            'csc_plate_visible_valid','photos_documented'
        ) NOT NULL");
        DB::statement('ALTER TABLE inquiry_checklists ADD UNIQUE KEY inquiry_checklists_inquiry_id_checklist_item_unique (inquiry_id, checklist_item)');
    }
};
