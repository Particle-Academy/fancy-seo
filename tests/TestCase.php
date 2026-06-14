<?php

namespace FancySeo\Tests;

use FancySeo\FancySeoServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [FancySeoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('app.name', 'Example');
        $app['config']->set('fancy-seo.site_name', 'Example');
        $app['config']->set('fancy-seo.url', 'https://example.test');
    }
}
