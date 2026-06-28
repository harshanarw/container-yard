<?php

namespace App\Mail;

use App\Models\ApCreditNote;
use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApCreditNoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public CompanySetting $company;
    public string $title = 'Credit Note';
    public string $partyName;

    public function __construct(
        public ApCreditNote $creditNote,
        public ?string $customMessage = null,
        public string $size = 'a4',
    ) {
        $this->company   = CompanySetting::current();
        $this->creditNote->loadMissing(['supplier', 'lines', 'createdBy']);
        $this->partyName = $this->creditNote->supplier->name ?? 'Supplier';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "AP Credit Note {$this->creditNote->credit_note_no}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.credit-note', with: [
            'cn'        => $this->creditNote,
            'title'     => $this->title,
            'partyName' => $this->partyName,
        ]);
    }

    public function attachments(): array
    {
        $size  = $this->size === 'half' ? 'half' : 'a4';
        $paper = $size === 'half' ? 'a5' : 'a4';

        $pdf = Pdf::loadView('finance.credit-notes.pdf', [
                'cn'            => $this->creditNote,
                'title'         => 'CREDIT NOTE',
                'partyLabel'    => 'Received From',
                'partyName'     => $this->partyName,
                'taxLabel'      => 'Input VAT',
                'size'          => $size,
                'showSignature' => false,
            ])
            ->setPaper($paper, 'portrait')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false);

        $filename = 'APCreditNote-' . $this->creditNote->credit_note_no . ($size === 'half' ? '-slip' : '') . '.pdf';

        return [Attachment::fromData(fn () => $pdf->output(), $filename)->withMime('application/pdf')];
    }
}
