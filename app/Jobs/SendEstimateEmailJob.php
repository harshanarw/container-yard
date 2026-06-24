<?php

namespace App\Jobs;

use App\Mail\EstimateIssuedMail;
use App\Models\Estimate;
use App\Models\PortalToken;
use App\Services\EstimateMailService;
use App\Services\ExternalRecipientResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEstimateEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Estimate $estimate,
        public PortalToken $portalToken,
        public ?string $customMessage = null,
    ) {}

    public function handle(): void
    {
        $mailable = new EstimateIssuedMail($this->estimate, $this->portalToken, $this->customMessage);

        // Parse CC field (comma-separated, stored as plain string).
        $manualCc = $this->parseEmails($this->estimate->send_cc_email ?? '');

        // Parse TO field; the first address is the portal-token recipient (primaryTo).
        // Any additional TO addresses are demoted to CC so they all receive the email.
        $allTo = $this->parseEmails($this->estimate->send_to_email ?? '');
        if (count($allTo) > 1) {
            $manualCc = array_merge($manualCc, array_slice($allTo, 1));
        }

        $recipients = ExternalRecipientResolver::resolve(
            category: 'estimate',
            customerId: $this->estimate->customer_id,
            primaryTo: $this->portalToken->email,
            manualCc: $manualCc,
        );

        $pending = EstimateMailService::resolveMailer()->to($recipients['to']);

        if (!empty($recipients['cc'])) {
            $pending->cc($recipients['cc']);
        }

        $pending->send($mailable);
    }

    /** Split a comma/semicolon/newline-separated string into valid email addresses. */
    private function parseEmails(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL));

        return array_values(array_unique($parts));
    }
}
