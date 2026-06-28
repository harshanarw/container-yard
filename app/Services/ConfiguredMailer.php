<?php

namespace App\Services;

use App\Models\EmailConfig;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Generic configured-mailer resolver: looks up the active EmailConfig for the
 * given category (SMTP / Mailgun / SendGrid) and wires it into the 'dynamic'
 * mailer slot, falling back to the default mailer with TLS verification relaxed.
 *
 * Used by both EstimateMailService and SendInvoiceEmailJob so the driver-config
 * match block is not duplicated.
 */
class ConfiguredMailer
{
    public static function forCategory(string $category, string $scope = 'external'): Mailer
    {
        $config = EmailConfig::forCategory($category, $scope);

        if ($config && self::configureFromEmailConfig($config)) {
            // Drop any cached mailer so 'dynamic' is rebuilt from the config just
            // set — otherwise a persistent worker (or a prior failed attempt) can
            // hand back a stale/half-built transport.
            Mail::forgetMailers();
            return Mail::mailer('dynamic');
        }

        return self::defaultSslBypassMailer();
    }

    private static function defaultSslBypassMailer(): Mailer
    {
        $default = config('mail.default');
        $base    = config("mail.mailers.{$default}", []);

        config(['mail.mailers.dynamic' => array_merge($base, [
            'transport' => 'smtp-no-verify',
        ])]);

        Mail::forgetMailers();
        return Mail::mailer('dynamic');
    }

    private static function configureFromEmailConfig(EmailConfig $config): bool
    {
        $settings = match ($config->driver) {
            'smtp' => [
                'transport'  => 'smtp-no-verify',
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
                'transport'  => 'smtp',
                'host'       => 'smtp.sendgrid.net',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'apikey',
                'password'   => $config->sendgrid_api_key,
            ],
            'microsoft365' => [
                'transport'     => 'microsoft365',
                'host'          => $config->smtp_host ?? 'smtp.office365.com',
                'port'          => $config->smtp_port ?? 587,
                'username'      => $config->smtp_username,
                'tenant_id'     => $config->oauth2_tenant_id,
                'client_id'     => $config->oauth2_client_id,
                'client_secret' => $config->oauth2_client_secret,
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
