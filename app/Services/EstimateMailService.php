<?php

namespace App\Services;

use App\Models\CustomerEmailContact;
use App\Models\Estimate;
use Illuminate\Mail\Mailer;

/**
 * Centralises how repair-estimate emails are sent so every estimate-related
 * message (initial issue, reminder) resolves the same mailer and the same
 * recipient list. Without this the reminder path drifted: it used the default
 * mailer and ignored the customer's configured 'estimate' contacts.
 */
class EstimateMailService
{
    public static function resolveMailer(): Mailer
    {
        return ConfiguredMailer::forCategory('estimate');
    }

    /**
     * Build a de-duplicated CC list for an estimate email: any manual CC
     * addresses plus the customer's configured 'estimate' email contacts.
     * TO-type customer contacts are added as CC because the portal-token
     * holder is always the primary (To) recipient.
     *
     * @param  string[]  $manualCc
     * @return string[]
     */
    public static function ccList(Estimate $estimate, array $manualCc = []): array
    {
        $cc = array_filter($manualCc);

        if ($estimate->customer_id) {
            $contacts = CustomerEmailContact::forCustomerCategory($estimate->customer_id, 'estimate');
            foreach ($contacts->where('address_type', 'to') as $c) {
                $cc[] = $c->email;
            }
            foreach ($contacts->where('address_type', 'cc') as $c) {
                $cc[] = $c->email;
            }
        }

        return array_values(array_unique($cc));
    }
}
