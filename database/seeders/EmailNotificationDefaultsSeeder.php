<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerEmailContact;
use App\Models\EmailConfig;
use App\Models\InternalNotificationEmail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds default email-notification data for all three directions of the email
 * system. Category keys are read from config/email_categories.php so this stays
 * the single source of truth — there is no hard-coded category list to drift.
 *
 *   COMMON   (email_configs)                  — a disabled 'general' template
 *   carrying example common-CC addresses so the structure exists out of the box.
 *
 *   INTERNAL (internal_notification_emails)   — yard-staff recipient lists, one
 *   default "to" address per category, linked to the operational modules that
 *   trigger them.
 *
 *   EXTERNAL (customer_email_contacts)        — per-customer recipient lists,
 *   seeded from each customer's primary email so existing customers have a
 *   sensible default the moment the feature goes live.
 *
 * Idempotent: re-running never duplicates rows and never overwrites manual edits.
 */
class EmailNotificationDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCommonConfig();
        $this->seedInternal();
        $this->seedExternal();
    }

    /**
     * A disabled 'general' email_config template carrying example common-CC
     * addresses. Admins fill in real driver credentials and activate it; the
     * example CC list is then copied onto every category that has no config of
     * its own (EmailConfig::forCategory falls back to 'general').
     *
     * Created inactive so it can never attempt a send with placeholder settings,
     * and only when no 'general' config already exists — manual configs are
     * never disturbed.
     */
    private function seedCommonConfig(): void
    {
        if (! Schema::hasTable('email_configs')) {
            return;
        }

        $defaultEmail = CompanySetting::current()->email ?: 'sysadmin@containeryard.com';
        $domain       = str_contains($defaultEmail, '@') ? explode('@', $defaultEmail)[1] : 'containeryard.com';

        EmailConfig::firstOrCreate(
            ['category' => 'general'],
            [
                'name'       => 'Default (General) — configure & activate',
                'driver'     => 'smtp',
                'is_default' => true,
                'is_active'  => false, // admin must add credentials and enable
                'cc_emails'  => [
                    "accounts@{$domain}",
                    "manager@{$domain}",
                ],
            ]
        );
    }

    /**
     * One default "to" recipient per internal category, addressed to the
     * configured company email (falls back to the seeded sysadmin mailbox).
     * Categories come from config('email_categories.internal').
     */
    private function seedInternal(): void
    {
        if (! Schema::hasTable('internal_notification_emails')) {
            return;
        }

        $defaultEmail = CompanySetting::current()->email ?: 'sysadmin@containeryard.com';
        $sort = 0;

        foreach (array_keys(config('email_categories.internal', [])) as $category) {
            InternalNotificationEmail::firstOrCreate(
                [
                    'category'     => $category,
                    'email'        => $defaultEmail,
                    'address_type' => 'to',
                ],
                [
                    'label'      => 'Default Recipient',
                    'is_active'  => true,
                    'sort_order' => $sort++,
                ]
            );
        }
    }

    /**
     * For every customer that has a primary email, seed a default "to" contact
     * per external category — but only when that customer/category has no
     * contact yet, so manual entries are never disturbed.
     * Categories come from config('email_categories.customer').
     */
    private function seedExternal(): void
    {
        if (! Schema::hasTable('customer_email_contacts')) {
            return;
        }

        $categories = array_keys(config('email_categories.customer', []));

        if (empty($categories)) {
            return;
        }

        Customer::whereNotNull('email')
            ->where('email', '!=', '')
            ->select(['id', 'email', 'contact_person'])
            ->chunkById(200, function ($customers) use ($categories) {
                foreach ($customers as $customer) {
                    foreach ($categories as $category) {
                        $exists = CustomerEmailContact::where('customer_id', $customer->id)
                            ->where('category', $category)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        CustomerEmailContact::create([
                            'customer_id'  => $customer->id,
                            'category'     => $category,
                            'email'        => $customer->email,
                            'label'        => $customer->contact_person ?: 'Primary Contact',
                            'address_type' => 'to',
                            'is_active'    => true,
                            'sort_order'   => 0,
                        ]);
                    }
                }
            });
    }
}
