<?php

namespace Ritterg\ExceptionMailer\Facades;

use Illuminate\Support\Facades\Facade;
use Ritterg\ExceptionMailer\ExceptionMailer as ExceptionMailerService;

/**
 * @method static void report(\Throwable $e)
 *
 * @see \Ritterg\ExceptionMailer\ExceptionMailer
 */
class ExceptionMailer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExceptionMailerService::class;
    }
}
