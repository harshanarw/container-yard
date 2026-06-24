<?php

namespace App\Providers;

use App\Models\ApprovalRequest;
use App\Models\CompanySetting;
use App\Models\Container;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\GuardCapture;
use App\Models\Inquiry;
use App\Models\Permission;
use App\Models\ReeferElectricityInvoice;
use App\Models\ReeferPlugSession;
use App\Models\ReeferTempLog;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
use App\Models\WorkOrder;
use App\Models\YardJob;
use App\Observers\ApprovalRequestObserver;
use App\Observers\ContainerObserver;
use App\Observers\EstimateObserver;
use App\Observers\GateMovementObserver;
use App\Observers\GuardCaptureObserver;
use App\Observers\InquiryObserver;
use App\Observers\ReeferElectricityInvoiceObserver;
use App\Observers\ReeferPlugSessionObserver;
use App\Observers\ReeferTempLogObserver;
use App\Observers\RepairInvoiceObserver;
use App\Observers\StorageHandlingInvoiceObserver;
use App\Observers\StorageInvoiceObserver;
use App\Observers\WorkOrderObserver;
use App\Observers\YardJobObserver;
use App\Services\DocumentStorage\DocumentManager;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Services\Mail\Microsoft365Token;
use Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator;
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
        // ── Audit log model observers ────────────────────────────────────────
        GateMovement::observe(GateMovementObserver::class);
        YardJob::observe(YardJobObserver::class);
        Container::observe(ContainerObserver::class);
        ReeferPlugSession::observe(ReeferPlugSessionObserver::class);
        ReeferTempLog::observe(ReeferTempLogObserver::class);
        Inquiry::observe(InquiryObserver::class);
        Estimate::observe(EstimateObserver::class);
        WorkOrder::observe(WorkOrderObserver::class);
        RepairInvoice::observe(RepairInvoiceObserver::class);
        StorageInvoice::observe(StorageInvoiceObserver::class);
        StorageHandlingInvoice::observe(StorageHandlingInvoiceObserver::class);
        ReeferElectricityInvoice::observe(ReeferElectricityInvoiceObserver::class);
        GuardCapture::observe(GuardCaptureObserver::class);
        ApprovalRequest::observe(ApprovalRequestObserver::class);

        // Register WebSocket channel auth route + channel definitions
        if (config('broadcasting.default') !== 'null') {
            Broadcast::routes(['middleware' => ['web', 'auth']]);
            if (file_exists(base_path('routes/channels.php'))) {
                require base_path('routes/channels.php');
            }
        }

        $this->registerGates();

        View::composer('*', function ($view) {
            try {
                $view->with('companySetting', CompanySetting::current());
            } catch (\Exception $e) {
                // Table may not exist yet during migrations
                $view->with('companySetting', null);
            }
        });

        // Microsoft 365 SMTP via XOAUTH2 (Client Credentials OAuth2 flow).
        // Fetches a Bearer token from Azure AD, then authenticates to Office 365
        // SMTP using only the XOAuth2Authenticator so the LOGIN method is never
        // attempted with a token that would be rejected by that mechanism.
        Mail::extend('microsoft365', function (array $config) {
            $token = Microsoft365Token::get(
                $config['tenant_id']     ?? '',
                $config['client_id']     ?? '',
                $config['client_secret'] ?? '',
            );

            $stream = new SocketStream();
            $stream->setStreamOptions([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            // Port 587 with STARTTLS: tls=false (STARTTLS is negotiated post-connect)
            $transport = new EsmtpTransport(
                $config['host'] ?? 'smtp.office365.com',
                (int) ($config['port'] ?? 587),
                false,   // not implicit TLS — STARTTLS
                null,
                null,
                $stream,
                [new XOAuth2Authenticator()],  // skip LOGIN; go straight to XOAUTH2
            );

            $transport->setUsername($config['username'] ?? '');
            $transport->setPassword($token);

            return $transport;
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
        // Super-user bypass — System Admin passes everything; Administrator passes
        // everything EXCEPT modules flagged system_only in config/modules.php.
        Gate::before(function ($user, string $ability) {
            if (!method_exists($user, 'isSuperUser') || !$user->isSuperUser()) {
                return null;
            }

            if ($user->isSystemAdmin()) {
                return true; // Full bypass — no restrictions
            }

            // Administrator: deny system-only modules so the yard-staff admin
            // cannot reach service-provider-level configuration.
            if ($user->isAdmin()) {
                $systemOnlyModules = collect(config('modules', []))
                    ->filter(fn($m) => !empty($m['system_only']))
                    ->keys()
                    ->toArray();

                foreach ($systemOnlyModules as $module) {
                    if ($ability === $module || str_starts_with($ability, $module . '.')) {
                        return false; // Hard deny — bypasses Gate::define callbacks
                    }
                }

                return true; // Admin bypasses all other permission checks
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
