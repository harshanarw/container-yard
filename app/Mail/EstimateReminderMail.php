<?php

namespace App\Mail;

use App\Models\Estimate;
use App\Models\PortalToken;
use App\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EstimateReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public CompanySetting $company;

    public function __construct(
        public Estimate $estimate,
        public PortalToken $portalToken,
    ) {
        $this->company = CompanySetting::current();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: Action Required — Estimate {$this->estimate->estimate_no}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.estimate-reminder',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
