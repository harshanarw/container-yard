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
        $config   = EmailConfig::forCategory('estimate');
        $mailable = new EstimateIssuedMail($this->estimate, $this->portalToken, $this->customMessage);

        $useCustomMailer = $config && $this->configureMailer($config);

        $mailerInstance = $useCustomMailer
            ? Mail::mailer('dynamic')
            : $this->applyDefaultSslBypass();

        $pending = $mailerInstance->to($this->portalToken->email);

        if ($this->estimate->send_cc_email) {
            $pending->cc($this->estimate->send_cc_email);
        }

        $pending->send($mailable);
    }

    private function applyDefaultSslBypass(): \Illuminate\Mail\Mailer
    {
        $default = config('mail.default');
        config(["mail.mailers.{$default}.stream" => [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]]);

        return Mail::mailer($default);
    }

    private function configureMailer(EmailConfig $config): bool
    {
        $settings = match($config->driver) {
            'smtp' => [
                'transport'  => 'smtp',
                'host'       => $config->smtp_host,
                'port'       => $config->smtp_port ?? 587,
                'encryption' => $config->smtp_encryption === 'none' ? null : $config->smtp_encryption,
                'username'   => $config->smtp_username,
                'password'   => $config->smtp_password,
                'stream'     => [
                    'ssl' => [
                        'verify_peer'      => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ],
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

        if (empty($settings)) {
            return false;
        }

        config(['mail.mailers.dynamic' => $settings]);

        if ($config->from_email) {
            config(['mail.from.address' => $config->from_email]);
            config(['mail.from.name' => $config->from_name ?? config('mail.from.name')]);
        }

        return true;
    }
}
