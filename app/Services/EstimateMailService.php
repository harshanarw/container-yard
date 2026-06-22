<?php

namespace App\Services;

use App\Models\CustomerEmailContact;
use App\Models\EmailConfig;
use App\Models\Estimate;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Centralises how repair-estimate emails are sent so every estimate-related
 * message (initial issue, reminder) resolves the same mailer and the same
 * recipient list. Without this the reminder path drifted: it used the default
 * mailer and ignored the customer's configured 'estimate' contacts.
 */
class EstimateMailService
{
    /**
     * Resolve the mailer for estimate emails: the active EmailConfig for the
     * 'estimate' category (SMTP / Mailgun / SendGrid) when one is configured,
     * otherwise the default mailer with TLS verification relaxed.
     */
    public static function resolveMailer(): Mailer
    {
        $config = EmailConfig::forCategory('estimate');

        if ($config && self::configureFromEmailConfig($config)) {
            return Mail::mailer('dynamic');
        }

        return self::defaultSslBypassMailer();
    }

    /**
     * Build a de-duplicated CC list for an estimate email: any manual CC
     * addresses plus the customer's configured 'estimate' email contacts.
     * TO-type customer contacts are added as CC because the portal-token
     * holder is always the primary (To) recipient.
     *
     * @param  string[]  $manualCc
     * @return string[]
     */
    public static function ccList(Estimate $estimate, array $manualCc = []): array
    {
        $cc = array_filter($manualCc);

        if ($estimate->customer_id) {
            $contacts = CustomerEmailContact::forCustomerCategory($estimate->customer_id, 'estimate');
            foreach ($contacts->where('address_type', 'to') as $c) {
                $cc[] = $c->email;
            }
            foreach ($contacts->where('address_type', 'cc') as $c) {
                $cc[] = $c->email;
            }
        }

        return array_values(array_unique($cc));
    }

    private static function defaultSslBypassMailer(): Mailer
    {
        $default = config('mail.default');
        $base    = config("mail.mailers.{$default}", []);

        config(['mail.mailers.dynamic' => array_merge($base, [
            'transport' => 'smtp-no-verify',
        ])]);

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
