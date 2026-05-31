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
    public int $photoCount = 0;

    public function __construct(
        public Estimate $estimate,
        public PortalToken $portalToken,
        public ?string $customMessage = null,
    ) {
        $this->company = CompanySetting::current();

        if ($this->estimate->attach_photos && $this->estimate->inquiry_id) {
            $inquiry = $this->estimate->inquiry;
            if ($inquiry) {
                $this->photoCount =
                    $inquiry->documents()->where('mime_type', 'like', 'image/%')->count()
                    + $inquiry->photos()->count();
            }
        }
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
