<?php

namespace App\Providers;

use App\Models\CompanySetting;
use App\Services\DocumentStorage\DocumentManager;
use Illuminate\Support\Facades\Mail;
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
}
