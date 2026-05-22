<?php

namespace App\Providers;

use App\Models\CompanySetting;
use App\Services\DocumentStorage\DocumentManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
    }
}
