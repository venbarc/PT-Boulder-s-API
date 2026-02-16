<?php

namespace App\Providers;

use App\Services\PtEverywhereService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PtEverywhereService::class);
    }

    public function boot(): void
    {
        //
    }
}
