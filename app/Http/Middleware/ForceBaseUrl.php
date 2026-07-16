<?php

namespace App\Http\Middleware;

use App\Support\BaseUrl;
use Closure;
use Illuminate\Http\Request;

/**
 * Pins every generated URL to the operator-configured public base URL, and
 * normalises the incoming request host so signed verification links validate.
 * Runs early in the web group (before the `signed` middleware). No-op when no
 * base URL is configured.
 */
class ForceBaseUrl
{
    public function handle(Request $request, Closure $next)
    {
        $base = BaseUrl::resolve();
        if ($base) {
            BaseUrl::normalizeRequest($request, $base);
            BaseUrl::forceGeneration($base);
        }

        return $next($request);
    }
}
