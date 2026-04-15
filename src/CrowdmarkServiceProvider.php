<?php

namespace Waterloobae\CrowdmarkDashboard;

use Illuminate\Support\ServiceProvider;

class CrowdmarkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package defaults under services.crowdmark so the package
        // works out-of-the-box before the developer publishes the config.
        $this->mergeConfigFrom(
            __DIR__ . '/../config/crowdmark.php',
            'services.crowdmark'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // php artisan vendor:publish --tag=crowdmark-config
            $this->publishes([
                __DIR__ . '/../config/crowdmark.php' => config_path('crowdmark.php'),
            ], 'crowdmark-config');
        }
    }
}
