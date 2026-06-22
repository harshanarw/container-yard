<?php

namespace App\Jobs;

use App\Mail\EstimateIssuedMail;
use App\Models\Estimate;
use App\Models\PortalToken;
use App\Services\EstimateMailService;
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

        $manualCc = $this->estimate->send_cc_email ? [$this->estimate->send_cc_email] : [];
        $ccList   = EstimateMailService::ccList($this->estimate, $manualCc);

        $pending = EstimateMailService::resolveMailer()->to($this->portalToken->email);

        if (!empty($ccList)) {
            $pending->cc($ccList);
        }

        $pending->send($mailable);
    }
}
