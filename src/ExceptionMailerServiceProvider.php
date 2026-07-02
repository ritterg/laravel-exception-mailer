<?php

namespace Ritterg\ExceptionMailer;

use Illuminate\Support\ServiceProvider;
use Ritterg\ExceptionMailer\Console\SendTestExceptionCommand;

class ExceptionMailerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/exception-mailer.php', 'exception-mailer');

        $this->app->singleton(ExceptionMailer::class, fn () => new ExceptionMailer());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'exception-mailer');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/exception-mailer.php' => config_path('exception-mailer.php'),
            ], 'exception-mailer-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/exception-mailer'),
            ], 'exception-mailer-views');

            $this->commands([SendTestExceptionCommand::class]);
        }
    }
}
