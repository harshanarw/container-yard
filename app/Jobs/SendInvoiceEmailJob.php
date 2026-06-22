<?php

namespace App\Jobs;

use App\Mail\InvoiceIssuedMail;
use App\Models\StorageInvoice;
use App\Services\ConfiguredMailer;
use App\Services\ExternalRecipientResolver;
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

        // Billing-party-first: resolve invoice contacts against billing_party_id with fallback to customer_id.
        // Internal 'invoice' staff are copied (CC) on the customer email.
        $recipients = ExternalRecipientResolver::resolve(
            category: 'invoice',
            customerId: $this->invoice->billing_party_id ?? $this->invoice->customer_id,
            primaryTo: $this->toEmail,
            manualCc: $this->manualCc,
        );

        $pending = ConfiguredMailer::forCategory('invoice')->to($recipients['to']);

        if (!empty($recipients['cc'])) {
            $pending->cc($recipients['cc']);
        }

        $pending->send($mailable);
    }
}
