<?php

namespace Waterloobae\CrowdmarkApiLaravel\Providers;

use Illuminate\Support\ServiceProvider;

class CrowdmarkApiLaravelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'crowdmark-api-laravel');
    }
}
