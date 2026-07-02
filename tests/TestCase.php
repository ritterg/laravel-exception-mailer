<?php

namespace Ritterg\ExceptionMailer\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Ritterg\ExceptionMailer\ExceptionMailerServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ExceptionMailerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');

        $app['config']->set('exception-mailer.enabled', true);
        $app['config']->set('exception-mailer.to', 'dev@example.test');
        $app['config']->set('exception-mailer.cache_store', 'array');
        $app['config']->set('exception-mailer.dedup_minutes', 10);
        $app['config']->set('exception-mailer.max_per_minute', 20);
    }
}
