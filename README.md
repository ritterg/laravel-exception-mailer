# laravel-exception-mailer

Send Laravel exceptions by e-mail — with **deduplication**, a **global throttle**,
**bot filtering** and a full Symfony debug page as the mail body. A maintained,
framework-native replacement for the unmaintained `squareboat/sneaker` (which no
longer works on Laravel 11/12).

Distilled as a "best of" from several in-house solutions:

- **Atomic dedup** via `Cache::add()` on the file store — keeps working even while
  the database is down (the moment you most need the alert).
- **Global throttle** via `RateLimiter` — a hard cap across *all* mails so a cascade
  of distinct errors can't flood the inbox.
- **Symfony `HtmlErrorRenderer`** — the full debug page (stack trace, source context)
  as the e-mail body.
- **Optional 429 abuse alert** — a slim notification when an IP trips a request rate
  limit (off by default).
- **`exception-mailer:test`** — send a pseudo-error mail on demand to verify delivery.

## Requirements

- PHP ≥ 8.1
- Laravel 9 – 13

## Installation

The package is a public GitHub repository, installed via a Composer VCS entry
(not (yet) on Packagist). No authentication is required — this works on shared
hosting without tokens or deploy keys:

```bash
composer config repositories.exception-mailer vcs https://github.com/ritterg/laravel-exception-mailer.git
composer require ritterg/laravel-exception-mailer:^1.0
```

The service provider and `ExceptionMailer` facade are auto-discovered.

Optionally publish the config and/or views:

```bash
php artisan vendor:publish --tag=exception-mailer-config
php artisan vendor:publish --tag=exception-mailer-views
```

## Configuration

Set at least the master switch and a recipient in `.env`:

```dotenv
EXCEPTION_MAILER_ENABLED=true
EXCEPTION_MAILER_TO="you@example.com"          # comma-separated for several
# EXCEPTION_MAILER_CC=
# EXCEPTION_MAILER_BCC=
# EXCEPTION_MAILER_FROM=                        # defaults to mail.from
# EXCEPTION_MAILER_SUBJECT_PREFIX="My App"
# EXCEPTION_MAILER_SUBJECT_TAG="[Exception]"    # "" = no tag
# EXCEPTION_MAILER_DEDUP_MINUTES=10             # 0 = disable dedup
# EXCEPTION_MAILER_MAX_PER_MINUTE=20            # 0 = disable global throttle
# EXCEPTION_MAILER_CACHE_STORE=file             # DB-outage resilient
# EXCEPTION_MAILER_QUEUE=false                  # default: send synchronously
# EXCEPTION_MAILER_429_ENABLED=false            # optional abuse alert
# EXCEPTION_MAILER_429_DEDUP_MINUTES=10
```

See [`config/exception-mailer.php`](config/exception-mailer.php) for the full list,
including the `bot_needles` and `dont_report` arrays.

## Wiring it up

The package is wiring-agnostic — call `ExceptionMailer::report($e)` from wherever your
app reports exceptions. Pick the one that matches your skeleton:

### Laravel 11/12 slim skeleton — `bootstrap/app.php`

```php
use Ritterg\ExceptionMailer\Facades\ExceptionMailer;
use Throwable;

->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions) {
    $exceptions->report(fn (Throwable $e) => ExceptionMailer::report($e));
})
```

### Legacy skeleton — `app/Exceptions/Handler.php`

```php
use Ritterg\ExceptionMailer\Facades\ExceptionMailer;
use Throwable;

public function register(): void
{
    $this->reportable(fn (Throwable $e) => ExceptionMailer::report($e));
}
```

That's it. `report()` never throws: mail failures are logged, not propagated, and it
never recurses. Laravel's normal logging pipeline is untouched — this is only the
mail branch.

## Filtering the mails

Every subject starts with a fixed tag, so one mail rule catches all alerts from all
projects:

```
[Exception] My App: QueryException | production | https://example.com/person/745
[Exception] My App: Rate limit exceeded | 203.0.113.7
```

Each mail also carries machine-readable headers (single-line, max. 300 chars) for
header-based rules, IMAP searches or scripted triage:

| Header | Example |
|---|---|
| `X-Exception-Mailer` | `exception` or `rate-limit` |
| `X-Exception-App` / `X-Exception-Env` | `My App` / `production` |
| `X-Exception-Class` | `Illuminate\Database\QueryException` |
| `X-Exception-Location` | `app/Http/Controllers/PersonController.php:42` (first frame outside `vendor/`) |
| `X-Exception-Fingerprint` | `3f9c0a1b2d4e` (class + location; stable across messages) |
| `X-Exception-Message` | shortened exception message |
| `X-Exception-Url` / `X-Exception-Ip` | request URL / client IP (rate-limit alert) |

## Testing delivery

```bash
php artisan exception-mailer:test
```

Sends a synthetic exception mail to the configured recipient(s), bypassing the enabled
flag, dedup and throttle — a pure transport/recipient check. Fails loudly if no
recipient is configured.

## The optional 429 abuse alert

If your app defines request rate limits (throttle middleware producing HTTP 429) and
you set `EXCEPTION_MAILER_429_ENABLED=true`, a `TooManyRequestsHttpException` sends a
**slim** alert (IP, URL, method, user agent, retry-after) instead of the full debug
mail, deduplicated to **one mail per IP per window**. The package only sends the
notification — you define the limits yourself with `RateLimiter::for(...)`.

## Migrating from squareboat/sneaker

Per project:

```bash
composer remove squareboat/sneaker
composer config repositories.exception-mailer vcs https://github.com/ritterg/laravel-exception-mailer.git
composer require ritterg/laravel-exception-mailer:^1.0
```

1. Delete `config/sneaker.php`.
2. Remove any Sneaker calls from the exception handler
   (e.g. `app('sneaker')->captureException(...)`).
3. Add the `reportable()` one-liner (legacy) or the `withExceptions()` line (slim).
4. Replace the old `SNEAKER_*` env keys with `EXCEPTION_MAILER_*` (see above).
5. `php artisan config:clear`, then `php artisan exception-mailer:test` to confirm.

## Development

```bash
composer install
composer test      # Pest
composer analyse   # PHPStan / Larastan
```

## License

MIT
