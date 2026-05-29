<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('driver', ['smtp', 'mailgun', 'sendgrid'])->default('smtp');
            $table->enum('category', [
                'estimate', 'invoice', 'stock_report', 'movement_report', 'general'
            ])->default('general');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // SMTP settings
            $table->string('smtp_host', 255)->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->enum('smtp_encryption', ['tls', 'ssl', 'none'])->nullable();
            $table->string('smtp_username', 255)->nullable();
            $table->string('smtp_password', 255)->nullable();

            // Mailgun settings
            $table->string('mailgun_domain', 255)->nullable();
            $table->string('mailgun_secret', 255)->nullable();
            $table->string('mailgun_endpoint', 100)->default('api.mailgun.net');

            // SendGrid settings
            $table->string('sendgrid_api_key', 255)->nullable();

            // Sender identity
            $table->string('from_name', 150)->nullable();
            $table->string('from_email', 255)->nullable();
            $table->string('reply_to', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_configs');
    }
};
