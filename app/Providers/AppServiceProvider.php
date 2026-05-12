<?php

namespace App\Providers;

use App\Services\DocumentStorage\DocumentManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DocumentManager::class);
    }

    public function boot(): void
    {
        //
    }
}
