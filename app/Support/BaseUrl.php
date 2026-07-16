<?php

namespace App\Support;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Applies the operator-configured public base URL to Laravel's URL generation.
 *
 * The generator derives the host from the request/APP_URL, which behind a proxy
 * or on a multi-tenant subdomain can resolve to the wrong host — sending every
 * generated link (routes, signed verification links, portal/QR links, WhatsApp,
 * emailed links) out on the wrong domain. Pinning app_base_url fixes them all:
 *   • forceGeneration()  — every URL the app builds uses the right scheme+host.
 *   • normalizeRequest() — the incoming request's host/scheme is rewritten to
 *     match, so SIGNED links (whose signature hashes the request URL) validate.
 */
class BaseUrl
{
    /** The configured base URL (scheme://host), or null when unset. */
    public static function resolve(): ?string
    {
        try {
            return CompanySetting::current()?->appBaseUrl();
        } catch (\Throwable $e) {
            return null; // settings table not migrated yet
        }
    }

    /** Force the URL generator root + scheme to the configured base. */
    public static function forceGeneration(?string $base = null): void
    {
        $base ??= self::resolve();
        if (! $base) {
            return;
        }

        URL::forceRootUrl($base);
        if (str_starts_with($base, 'https://')) {
            URL::forceScheme('https');
        }
    }

    /** Rewrite the incoming request host/scheme to the base so signed-link
     *  validation matches links generated on this host. */
    public static function normalizeRequest(Request $request, ?string $base = null): void
    {
        $base ??= self::resolve();
        if (! $base) {
            return;
        }

        $parts = parse_url($base);
        $host  = ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if ($host === '') {
            return;
        }

        $request->headers->set('HOST', $host);
        $request->server->set('HTTP_HOST', $host);
        if (($parts['scheme'] ?? 'https') === 'https') {
            $request->server->set('HTTPS', 'on');
        }
    }
}
