<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->string('estimate_no', 20)->unique()->comment('Format: RE-XXXX');
            $table->foreignId('inquiry_id')->nullable()->constrained('inquiries')->nullOnDelete();
            $table->foreignId('container_id')->constrained('containers')->restrictOnDelete();
            $table->string('container_no', 12);
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->enum('size', ['20', '40', '45']);
            $table->enum('type_code', ['GP', 'HC', 'RF', 'OT', 'FR', 'TK']);
            $table->date('estimate_date');
            $table->date('valid_until');
            $table->enum('currency', ['MYR', 'USD', 'SGD'])->default('MYR');
            $table->enum('priority', [
                'normal',   // 7-14 days
                'urgent',   // 3-5 days
                'critical', // Next day
            ])->default('normal');
            $table->enum('status', [
                'draft', 'sent', 'approved', 'rejected', 'completed',
            ])->default('draft');
            $table->text('scope_of_work')->nullable();
            $table->text('terms')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('send_to_email')->nullable();
            $table->string('send_cc_email')->nullable();
            $table->text('email_message')->nullable();
            $table->boolean('attach_pdf')->default(true);
            $table->boolean('attach_photos')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_date')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
