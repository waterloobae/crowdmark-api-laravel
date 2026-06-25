<?php

namespace Waterloobae\CrowdmarkApiLaravel\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CrowdmarkApiLaravelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'crowdmark-api-laravel');

        if (!Route::has('crowdmark')) {
            Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
        }
    }
}
