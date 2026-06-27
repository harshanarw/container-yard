<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public CompanySetting $company;

    public function __construct(
        public Receipt $receipt,
        public ?string $customMessage = null,
        public string $size = 'a4',
    ) {
        $this->company = CompanySetting::current();
        $this->receipt->loadMissing(['customer', 'bankAccount', 'allocations', 'createdBy']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Receipt {$this->receipt->receipt_no}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.receipt');
    }

    public function attachments(): array
    {
        $size  = $this->size === 'half' ? 'half' : 'a4';
        $paper = $size === 'half' ? 'a5' : 'a4';

        $pdf = Pdf::loadView('finance.receipts.pdf', [
                'receipt'       => $this->receipt,
                'size'          => $size,
                'showSignature' => false, // digital copy — no manual signature lines
            ])
            ->setPaper($paper, 'portrait')
            ->set_option('defaultFont', 'sans-serif')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false);

        $filename = 'Receipt-' . $this->receipt->receipt_no . ($size === 'half' ? '-slip' : '') . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
