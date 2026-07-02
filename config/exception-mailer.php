<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | Keep this off in local/testing and turn it on in production. When off,
    | no exception mail is ever sent (Laravel's normal logging is untouched).
    */
    'enabled' => env('EXCEPTION_MAILER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Recipients
    |--------------------------------------------------------------------------
    | Comma-separated lists are allowed for every address key. "from" may be
    | left null to fall back to the application's default mail.from address.
    */
    'to'   => env('EXCEPTION_MAILER_TO'),
    'cc'   => env('EXCEPTION_MAILER_CC'),
    'bcc'  => env('EXCEPTION_MAILER_BCC'),
    'from' => env('EXCEPTION_MAILER_FROM'),

    'subject_prefix' => env('EXCEPTION_MAILER_SUBJECT_PREFIX', config('app.name')),

    /*
    |--------------------------------------------------------------------------
    | Deduplication
    |--------------------------------------------------------------------------
    | An identical exception (same class + message) is mailed only once per
    | this window. Set to 0 to disable deduplication entirely.
    */
    'dedup_minutes' => (int) env('EXCEPTION_MAILER_DEDUP_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Global throttle
    |--------------------------------------------------------------------------
    | Hard cap across ALL exception mails (even distinct ones) per minute, so
    | a cascade of different errors can't flood the inbox. 0 disables the cap.
    */
    'max_per_minute' => (int) env('EXCEPTION_MAILER_MAX_PER_MINUTE', 20),

    /*
    |--------------------------------------------------------------------------
    | Cache store
    |--------------------------------------------------------------------------
    | Store used for dedup keys and the throttle counter. "file" is the default
    | on purpose: it keeps working even while the database is unavailable — the
    | exact moment you most need the alert to go out.
    */
    'cache_store' => env('EXCEPTION_MAILER_CACHE_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Default is synchronous send so alerts still go out when the queue/DB is
    | down. Enable only if you have a reliable, separate queue transport.
    */
    'queue' => env('EXCEPTION_MAILER_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Bot filter
    |--------------------------------------------------------------------------
    | Requests whose User-Agent contains one of these needles are ignored, to
    | mute crawler-triggered noise. Matching is case-insensitive.
    */
    'bot_needles' => ['googlebot', 'bingbot', 'yandexbot', 'slurp', 'ia_archiver'],

    /*
    |--------------------------------------------------------------------------
    | Never reported by mail
    |--------------------------------------------------------------------------
    | Expected exceptions that should never produce an alert. Note that
    | TooManyRequestsHttpException is intentionally NOT listed here: it is
    | routed to the optional rate-limit alert below.
    */
    'dont_report' => [
        AuthenticationException::class,
        ValidationException::class,
        NotFoundHttpException::class,
    ],

    'view' => 'exception-mailer::mail.exception',

    /*
    |--------------------------------------------------------------------------
    | Rate-limit (429) abuse alert — optional, default OFF
    |--------------------------------------------------------------------------
    | Only fires when the project itself defines request rate limits (throttle
    | middleware producing a 429). This package sends the notification only;
    | it does not define any limits. Deduplicated to one mail per IP per window.
    */
    'rate_limit_alert' => [
        'enabled'       => env('EXCEPTION_MAILER_429_ENABLED', false),
        'dedup_minutes' => (int) env('EXCEPTION_MAILER_429_DEDUP_MINUTES', 10),
        'view'          => 'exception-mailer::mail.rate-limit',
    ],

];
