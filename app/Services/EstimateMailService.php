<?php

namespace App\Services;

use Illuminate\Mail\Mailer;

/**
 * Resolves the mailer used for repair-estimate emails so the initial issue and
 * reminder paths send through the same (estimate-category) configuration.
 * Recipient composition lives in ExternalRecipientResolver.
 */
class EstimateMailService
{
    public static function resolveMailer(): Mailer
    {
        return ConfiguredMailer::forCategory('estimate');
    }
}
