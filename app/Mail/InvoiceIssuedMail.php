<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\StorageInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public CompanySetting $company;

    public function __construct(
        public StorageInvoice $invoice,
        public ?string $customMessage = null,
    ) {
        $this->company = CompanySetting::current();
        $this->invoice->loadMissing(['customer', 'billingParty', 'details', 'createdBy']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Storage Invoice {$this->invoice->invoice_no}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invoice-issued',
        );
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('billing.pdf', ['invoice' => $this->invoice])
            ->setPaper('a4', 'landscape')
            ->set_option('defaultFont', 'sans-serif')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false);

        $filename = 'Invoice-' . $this->invoice->invoice_no . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
