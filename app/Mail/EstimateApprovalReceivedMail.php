<?php

namespace App\Mail;

use App\Models\Estimate;
use App\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EstimateApprovalReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public CompanySetting $company;

    public function __construct(
        public Estimate $estimate,
        public string $action,
        public ?string $ownerNotes = null,
    ) {
        $this->company = CompanySetting::current();
    }

    public function envelope(): Envelope
    {
        $label = match($this->action) {
            'approved'           => 'Approved',
            'rejected'           => 'Rejected',
            'partially_approved' => 'Partially Approved',
            default              => ucfirst($this->action),
        };

        return new Envelope(
            subject: "Estimate {$this->estimate->estimate_no} {$label} by Owner",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.estimate-approval-received',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
