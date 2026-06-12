<?php

namespace App\Providers;

use App\Models\CompanySetting;
use App\Models\Permission;
use App\Services\DocumentStorage\DocumentManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DocumentManager::class);
    }

    public function boot(): void
    {
        $this->registerGates();

        View::composer('*', function ($view) {
            try {
                $view->with('companySetting', CompanySetting::current());
            } catch (\Exception $e) {
                // Table may not exist yet during migrations
                $view->with('companySetting', null);
            }
        });

        // Custom SMTP transport that skips SSL certificate peer verification.
        // Needed for shared hosting where the mail server certificate CN does
        // not match the configured hostname (e.g. *.gohsphere.com vs mail.example.com).
        Mail::extend('smtp-no-verify', function (array $config) {
            $tls = ($config['encryption'] ?? null) === 'ssl';

            $stream = new SocketStream();
            $stream->setStreamOptions([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $transport = new EsmtpTransport(
                $config['host']     ?? '127.0.0.1',
                (int) ($config['port'] ?? ($tls ? 465 : 587)),
                $tls,
                null,
                null,
                $stream,
            );

            if (!empty($config['username'])) {
                $transport->setUsername($config['username']);
            }
            if (!empty($config['password'])) {
                $transport->setPassword($config['password']);
            }

            return $transport;
        });
    }

    private function registerGates(): void
    {
        // Super-admin / system-administrator bypass all permission checks
        Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'isSuperUser') && $user->isSuperUser()) {
                return true;
            }
        });

        // Register every permission from the DB as a named Gate ability so that
        // @can('billing.reefer.create') and ->middleware('can:billing.reefer.create') work.
        // Permission names are cached for 1 hour; cleared by SyncPermissions command.
        try {
            if (!Schema::hasTable('permissions')) {
                return;
            }

            $names = Cache::remember('_gate_permission_names', 3600, fn() =>
                Permission::pluck('name')->toArray()
            );

            foreach ($names as $name) {
                Gate::define($name, fn($user) => $user->hasPermissionTo($name));
            }
        } catch (\Throwable) {
            // DB not ready (fresh install / migrations not yet run)
        }
    }
}
