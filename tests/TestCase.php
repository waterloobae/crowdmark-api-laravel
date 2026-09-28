<?php

namespace Waterloobae\CrowdmarkApiLaravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Waterloobae\CrowdmarkApiLaravel\Providers\CrowdmarkApiLaravelServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CrowdmarkApiLaravelServiceProvider::class];
    }
}