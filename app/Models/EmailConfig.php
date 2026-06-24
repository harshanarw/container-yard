<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailConfig extends Model
{
    protected $fillable = [
        'name', 'driver', 'category', 'scope', 'is_default', 'is_active',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'mailgun_domain', 'mailgun_secret', 'mailgun_endpoint',
        'sendgrid_api_key',
        'oauth2_tenant_id', 'oauth2_client_id', 'oauth2_client_secret',
        'from_name', 'from_email', 'reply_to', 'cc_emails',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
        'smtp_port'  => 'integer',
        'cc_emails'  => 'array',
    ];

    protected $hidden = [
        'smtp_password', 'mailgun_secret', 'sendgrid_api_key', 'oauth2_client_secret',
    ];

    /**
     * Resolve the active sender config for a category and direction.
     *
     *  - scope 'external' (default): the category's own config, else the
     *    external 'general' config (historical behaviour).
     *  - scope 'internal': the dedicated internal sender if one is active,
     *    otherwise it falls back to the external 'general' config so internal
     *    notifications keep sending even when no internal server is set up.
     */
    public static function forCategory(string $category, string $scope = 'external'): ?self
    {
        if ($scope === 'internal') {
            // Internal sender for this category, else the internal 'general'
            // sender, else fall back to the external 'general' sender.
            return static::where('scope', 'internal')
                ->where('category', $category)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->first()
                ?? static::where('scope', 'internal')
                    ->where('category', 'general')
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->first()
                ?? static::externalGeneral();
        }

        return static::where('scope', 'external')
            ->where('category', $category)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first()
            ?? static::externalGeneral();
    }

    /** The active external 'general' config, used as the universal fallback. */
    private static function externalGeneral(): ?self
    {
        return static::where('scope', 'external')
            ->where('category', 'general')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();
    }

    /**
     * Common CC addresses for external sends of a category — falls back to the
     * 'general' config (same resolution order as forCategory).
     *
     * @return string[]
     */
    public static function commonCc(string $category): array
    {
        $config = static::forCategory($category);

        return array_values(array_filter($config?->cc_emails ?? []));
    }
}
