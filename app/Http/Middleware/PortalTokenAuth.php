<?php

namespace App\Http\Middleware;

use App\Models\PortalToken;
use Closure;
use Illuminate\Http\Request;

class PortalTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->route('token') ?? $request->query('token');

        if (!$token) {
            return redirect()->route('portal.login')->with('error', 'Access token required.');
        }

        $portalToken = PortalToken::where('token', $token)->first();

        if (!$portalToken || !$portalToken->isValid()) {
            return redirect()->route('portal.login')->with('error', 'This link has expired or is no longer valid.');
        }

        $portalToken->markAccessed();
        $request->attributes->set('portal_token', $portalToken);

        return $next($request);
    }
}
