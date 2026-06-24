<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fetches and caches a Microsoft 365 OAuth2 access token using the
 * Client Credentials flow (application-level auth, no user password).
 *
 * Azure AD app prerequisites:
 *   - App Registration in the tenant
 *   - API permission: Office 365 Exchange Online → SMTP.SendAsApp (Application)
 *   - Admin consent granted for the above permission
 *   - Per-mailbox SMTP AUTH enabled in Exchange Admin Center
 */
class Microsoft365Token
{
    private const TOKEN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';
    private const SCOPE     = 'https://outlook.office365.com/.default';
    // Cache for 58 minutes — tokens are valid for 60 minutes; leave a 2-minute buffer.
    private const CACHE_TTL = 3480;

    /**
     * Returns a valid Bearer token for the given Azure AD app, fetching a new
     * one only when the cached token has expired.
     *
     * @throws \RuntimeException if the token endpoint returns an error
     */
    public static function get(string $tenantId, string $clientId, string $clientSecret): string
    {
        $cacheKey = 'ms365_oauth_token_' . sha1($tenantId . '|' . $clientId);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId, $clientId, $clientSecret) {
            $response = Http::asForm()
                ->timeout(15)
                ->post(sprintf(self::TOKEN_URL, urlencode($tenantId)), [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'scope'         => self::SCOPE,
                ]);

            if ($response->failed() || empty($response->json('access_token'))) {
                $desc = $response->json('error_description')
                    ?? $response->json('error')
                    ?? ('HTTP ' . $response->status());

                throw new \RuntimeException(
                    "Microsoft 365 OAuth2 token request failed: {$desc}"
                );
            }

            return $response->json('access_token');
        });
    }

    /**
     * Invalidate the cached token for a given app — call this when you receive
     * a 535 after using a previously-cached token (handles early revocation).
     */
    public static function forget(string $tenantId, string $clientId): void
    {
        Cache::forget('ms365_oauth_token_' . sha1($tenantId . '|' . $clientId));
    }
}
