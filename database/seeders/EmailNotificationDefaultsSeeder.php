<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerEmailContact;
use App\Models\InternalNotificationEmail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds default email-notification categories for both directions:
 *
 *   INTERNAL (internal_notification_emails)  — yard-staff recipient lists, one
 *   default "to" address per category, linked to the operational modules that
 *   trigger them.
 *
 *   EXTERNAL (customer_email_contacts)        — per-customer recipient lists,
 *   seeded from each customer's primary email so existing customers have a
 *   sensible default the moment the feature goes live.
 *
 * Idempotent: re-running never duplicates rows.
 */
class EmailNotificationDefaultsSeeder extends Seeder
{
    /**
     * Internal categories → module they belong to.
     * Keep these keys in sync with InternalNotificationEmailController validation
     * and the settings/email-config view.
     */
    private const INTERNAL_CATEGORIES = [
        'estimate_approval' => 'Repair Estimate — customer approval / rejection alerts',
        'invoice'           => 'Invoicing — billing notifications',
        'movement_report'   => 'Gate / Yard — container movement reports',
        'general'           => 'System — general fallback notifications',
    ];

    /**
     * External (customer-facing) categories → module they belong to.
     * Keep in sync with CustomerEmailContactController validation and customers/show view.
     */
    private const EXTERNAL_CATEGORIES = [
        'estimate'        => 'Repair Estimate sent to customer',
        'invoice'         => 'Invoice sent to customer',
        'movement_report' => 'Movement reports sent to customer',
    ];

    public function run(): void
    {
        $this->seedInternal();
        $this->seedExternal();
    }

    /**
     * One default "to" recipient per internal category, addressed to the
     * configured company email (falls back to the seeded sysadmin mailbox).
     */
    private function seedInternal(): void
    {
        if (! Schema::hasTable('internal_notification_emails')) {
            return;
        }

        $defaultEmail = CompanySetting::current()->email ?: 'sysadmin@containeryard.com';
        $sort = 0;

        foreach (self::INTERNAL_CATEGORIES as $category => $label) {
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
     */
    private function seedExternal(): void
    {
        if (! Schema::hasTable('customer_email_contacts')) {
            return;
        }

        Customer::whereNotNull('email')
            ->where('email', '!=', '')
            ->select(['id', 'email', 'contact_person'])
            ->chunkById(200, function ($customers) {
                foreach ($customers as $customer) {
                    foreach (array_keys(self::EXTERNAL_CATEGORIES) as $category) {
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
