<?php

namespace Tests\Feature\Smoke;

use Illuminate\Support\Facades\Route;
use Tests\Support\FeatureTestCase;

/**
 * Broad, low-maintenance regression net: every parameter-less GET page must
 * render for a system administrator without a server (5xx) error. Because it
 * enumerates the route table at runtime, it automatically covers new screens
 * as they are added — catching "white screen" regressions (broken Blade,
 * missing bindings, fatal errors) across every module.
 *
 * A route that legitimately needs setup can be added to $skipUris below.
 */
class RouteSmokeTest extends FeatureTestCase
{
    /** URI prefixes to skip (public/auth, api, dev tools, file exports, portal). */
    private array $skipPrefixes = [
        'login', 'logout', 'password', 'register', 'email/verify',
        'api', 'telescope', '_debugbar', '_ignition', 'up',
        'g/', 'gp/', 'verify/',            // public / signed / driver links
        'portal',                           // separate customer-portal auth
    ];

    /** Exact URIs to skip (known to need query params or special state). */
    private array $skipUris = [];

    public function test_get_pages_render_without_server_error(): void
    {
        $this->actingAsSystemAdmin();

        $failures = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = ltrim($route->uri(), '/');

            if (str_contains($uri, '{')) continue;                 // parameterised
            if (in_array($uri, $this->skipUris, true)) continue;
            if ($this->prefixSkipped($uri)) continue;
            if ($this->isFileEndpoint($route->getName())) continue; // pdf/export/print

            try {
                $status = $this->get('/' . $uri)->getStatusCode();
            } catch (\Throwable $e) {
                $failures[] = "/{$uri} threw " . class_basename($e) . ': ' . $e->getMessage();
                continue;
            }

            // 200 / 302 / 403 are all "not broken"; 5xx is a real regression.
            if ($status >= 500) {
                $failures[] = "/{$uri} → HTTP {$status}";
            }
        }

        $this->assertSame(
            [],
            $failures,
            "The following pages returned a server error:\n  " . implode("\n  ", $failures)
        );
    }

    private function prefixSkipped(string $uri): bool
    {
        foreach ($this->skipPrefixes as $p) {
            if ($uri === rtrim($p, '/') || str_starts_with($uri, $p)) {
                return true;
            }
        }

        return false;
    }

    private function isFileEndpoint(?string $name): bool
    {
        return $name !== null
            && (bool) preg_match('/(pdf|print|export|download|csv|codeco|ird-print|ird-tax)/', $name);
    }
}
