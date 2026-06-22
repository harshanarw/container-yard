<?php

namespace App\Services;

use App\Models\CustomerEmailContact;
use App\Models\EmailConfig;
use App\Models\InternalNotificationEmail;

/**
 * Single place that decides WHO receives an external (customer-facing) email
 * for a given category. Composes the To / CC lists from five sources:
 *
 *   1. Per-customer TO contacts   (customer_email_contacts, address_type=to)
 *   2. Per-customer CC contacts   (customer_email_contacts, address_type=cc)
 *   3. Common CC                  (email_configs.cc_emails for the category)
 *   4. Internal staff copy        (internal_notification_emails for the category)
 *   5. Manual CC                  (typed at the trigger point)
 *
 * When $primaryTo is given (estimate portal-token email, invoice billing-party
 * email) it becomes the sole To and every customer TO contact is demoted to CC.
 * When it is null, the customer TO contacts become the To list.
 *
 * $internalCategory lets a caller copy a different internal category than the
 * external one (defaults to the same key). The estimate flow leaves it as
 * 'estimate' — for which no internal category is configured by default, so the
 * tokenised approval email is NOT auto-copied to staff. Invoices map to the
 * existing internal 'invoice' recipients.
 */
class ExternalRecipientResolver
{
    /**
     * @param  string[]  $manualCc
     * @return array{to: string[], cc: string[]}
     */
    public static function resolve(
        string $category,
        ?int $customerId = null,
        ?string $primaryTo = null,
        array $manualCc = [],
        ?string $internalCategory = null,
    ): array {
        $internalCategory ??= $category;

        $to = [];
        $cc = [];

        $customerTo = [];
        $customerCc = [];
        if ($customerId) {
            $contacts   = CustomerEmailContact::forCustomerCategory($customerId, $category);
            $customerTo = $contacts->where('address_type', 'to')->pluck('email')->all();
            $customerCc = $contacts->where('address_type', 'cc')->pluck('email')->all();
        }

        if ($primaryTo) {
            $to[] = $primaryTo;
            // explicit primary recipient → customer TO contacts ride along as CC
            $cc = array_merge($cc, $customerTo);
        } else {
            $to = array_merge($to, $customerTo);
        }

        $cc = array_merge(
            $cc,
            $customerCc,
            EmailConfig::commonCc($category),
            InternalNotificationEmail::forCategory($internalCategory)->pluck('email')->all(),
            array_values($manualCc),
        );

        $to = self::clean($to);
        $cc = self::clean($cc);

        // never CC an address that is already a To recipient
        $toLower = array_map('strtolower', $to);
        $cc = array_values(array_filter($cc, fn ($e) => !in_array(strtolower($e), $toLower, true)));

        return ['to' => $to, 'cc' => $cc];
    }

    /** Trim, drop blanks/invalid, case-insensitively de-duplicate. */
    private static function clean(array $emails): array
    {
        $seen = [];
        $out  = [];
        foreach ($emails as $e) {
            $e = trim((string) $e);
            if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $key = strtolower($e);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[]      = $e;
        }

        return $out;
    }
}
