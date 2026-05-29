<?php

namespace App\Mail;

use App\Models\Estimate;
use App\Models\PortalToken;
use App\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class EstimateIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public CompanySetting $company;

    public function __construct(
        public Estimate $estimate,
        public PortalToken $portalToken,
        public ?string $customMessage = null,
    ) {
        $this->company = CompanySetting::current();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Repair Estimate {$this->estimate->estimate_no} — {$this->estimate->container_no}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.estimate-issued',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
