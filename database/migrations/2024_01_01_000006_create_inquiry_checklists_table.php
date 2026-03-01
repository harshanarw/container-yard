<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->enum('checklist_item', [
                'exterior_panels_inspected',
                'floor_board_condition_checked',
                'door_mechanism_tested',
                'door_seals_gaskets_checked',
                'roof_integrity_verified',
                'corner_castings_inspected',
                'base_rails_cross_members',
                'forklift_pockets_checked',
                'csc_plate_visible_valid',
                'photos_documented',
            ]);
            $table->boolean('is_checked')->default(false);
            $table->timestamps();

            $table->unique(['inquiry_id', 'checklist_item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_checklists');
    }
};
