<?php

namespace App\Jobs;

use App\Mail\InvoiceIssuedMail;
use App\Models\CustomerEmailContact;
use App\Models\InternalNotificationEmail;
use App\Models\StorageInvoice;
use App\Services\ConfiguredMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public StorageInvoice $invoice,
        public string $toEmail,
        public ?string $customMessage = null,
        public array $manualCc = [],
    ) {}

    public function handle(): void
    {
        $mailable = new InvoiceIssuedMail($this->invoice, $this->customMessage);

        // Billing-party-first: resolve invoice contacts against billing_party_id with fallback to customer_id
        $partyId  = $this->invoice->billing_party_id ?? $this->invoice->customer_id;
        $cc       = array_filter($this->manualCc);

        if ($partyId) {
            $contacts = CustomerEmailContact::forCustomerCategory($partyId, 'invoice');
            // TO-type contacts CC'd because $toEmail already holds the primary To
            foreach ($contacts->where('address_type', 'to') as $c) {
                $cc[] = $c->email;
            }
            foreach ($contacts->where('address_type', 'cc') as $c) {
                $cc[] = $c->email;
            }
        }

        // CC internal staff configured under the 'invoice' notification category
        $internalCc = InternalNotificationEmail::forCategory('invoice');
        foreach ($internalCc as $r) {
            $cc[] = $r->email;
        }

        $cc = array_values(array_unique(array_filter($cc)));

        $pending = ConfiguredMailer::forCategory('invoice')->to($this->toEmail);

        if (!empty($cc)) {
            $pending->cc($cc);
        }

        $pending->send($mailable);
    }
}
