<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\PaymentVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentVoucherMail extends Mailable
{
    use Queueable, SerializesModels;

    public CompanySetting $company;

    public function __construct(
        public PaymentVoucher $voucher,
        public ?string $customMessage = null,
        public string $size = 'a4',
    ) {
        $this->company = CompanySetting::current();
        $this->voucher->loadMissing(['supplier', 'bankAccount', 'allocations.invoice', 'createdBy']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Payment Voucher {$this->voucher->voucher_no}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payment-voucher');
    }

    public function attachments(): array
    {
        $size  = $this->size === 'half' ? 'half' : 'a4';
        $paper = $size === 'half' ? 'a5' : 'a4';

        $pdf = Pdf::loadView('finance.vouchers.pdf', [
                'voucher'       => $this->voucher,
                'size'          => $size,
                'showSignature' => false, // digital copy — no manual signature lines
            ])
            ->setPaper($paper, 'portrait')
            ->set_option('defaultFont', 'sans-serif')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false);

        $filename = 'Voucher-' . $this->voucher->voucher_no . ($size === 'half' ? '-slip' : '') . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
