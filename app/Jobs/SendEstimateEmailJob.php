<?php

namespace App\Jobs;

use App\Mail\EstimateIssuedMail;
use App\Models\EmailConfig;
use App\Models\Estimate;
use App\Models\PortalToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

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
        $config = EmailConfig::forCategory('estimate');
        $mailable = new EstimateIssuedMail($this->estimate, $this->portalToken, $this->customMessage);

        if ($config) {
            $this->configureMailer($config);
        }

        $mailer = Mail::to($this->portalToken->email);

        if ($this->estimate->send_cc_email) {
            $mailer->cc($this->estimate->send_cc_email);
        }

        $mailer->send($mailable);
    }

    private function configureMailer(EmailConfig $config): void
    {
        $settings = match($config->driver) {
            'smtp' => [
                'transport'  => 'smtp',
                'host'       => $config->smtp_host,
                'port'       => $config->smtp_port ?? 587,
                'encryption' => $config->smtp_encryption === 'none' ? null : $config->smtp_encryption,
                'username'   => $config->smtp_username,
                'password'   => $config->smtp_password,
            ],
            'mailgun' => [
                'transport' => 'mailgun',
                'domain'    => $config->mailgun_domain,
                'secret'    => $config->mailgun_secret,
                'endpoint'  => $config->mailgun_endpoint,
            ],
            'sendgrid' => [
                'transport' => 'smtp',
                'host'      => 'smtp.sendgrid.net',
                'port'      => 587,
                'encryption'=> 'tls',
                'username'  => 'apikey',
                'password'  => $config->sendgrid_api_key,
            ],
            default => [],
        };

        if (!empty($settings)) {
            config(['mail.mailers.dynamic' => $settings]);

            if ($config->from_email) {
                config(['mail.from.address' => $config->from_email]);
                config(['mail.from.name' => $config->from_name ?? config('mail.from.name')]);
            }
        }
    }
}
