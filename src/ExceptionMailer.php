<?php

namespace Ritterg\ExceptionMailer;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Ritterg\ExceptionMailer\Mail\ExceptionOccurred;
use Ritterg\ExceptionMailer\Mail\RateLimitExceeded;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Sends exceptions by e-mail with deduplication, a global throttle and bot
 * filtering. This service is only the "mail branch" — it never logs the
 * original exception itself; Laravel's normal report pipeline still does that.
 *
 * It is deliberately wiring-agnostic: call report() from a legacy
 * Handler::register() reportable(), from bootstrap/app.php withExceptions(),
 * or anywhere else. It never throws.
 */
class ExceptionMailer
{
    public function report(Throwable $e): void
    {
        try {
            // 0. Rate-limit (429) alerts take a separate, slimmer path.
            if ($e instanceof TooManyRequestsHttpException) {
                if ($this->config('rate_limit_alert.enabled')) {
                    $this->sendRateLimitAlert($e);
                }

                return;
            }

            if (! $this->config('enabled')) {
                return;
            }

            if ($this->isDontReport($e) || $this->isBotRequest()) {
                return;
            }

            // 4. Dedup: identical exceptions are muted for the configured window.
            if (! $this->passesDedup($e)) {
                return;
            }

            // 5. Throttle + 6. send.
            $this->sendThrottled(fn () => $this->send(new ExceptionOccurred($e)));
        } catch (Throwable $selfFailure) {
            // Never let the mailer break the app or recurse into itself.
            $this->logQuietly('exception-mailer: reporting failed', $selfFailure);
        }
    }

    /**
     * Send a synthetic test exception mail, bypassing the enabled flag, dedup
     * and throttle. A pure delivery/recipient check for `exception-mailer:test`.
     *
     * @return list<string> the resolved recipients
     *
     * @throws \RuntimeException when no recipient is configured
     */
    public function sendTest(): array
    {
        $to = $this->addresses($this->config('to'));

        if (empty($to)) {
            throw new \RuntimeException('No recipient configured (exception-mailer.to / EXCEPTION_MAILER_TO).');
        }

        $exception = new \RuntimeException(
            'Test exception from exception-mailer:test — if you received this, mail delivery works.'
        );

        $this->send(new ExceptionOccurred($exception));

        return $to;
    }

    protected function passesDedup(Throwable $e): bool
    {
        $minutes = (int) $this->config('dedup_minutes');

        if ($minutes <= 0) {
            return true;
        }

        $signature = 'exception-mailer:'.md5(get_class($e).'|'.$e->getMessage());

        // Cache::add is atomic: it succeeds only if the key does not yet exist.
        return $this->cache()->add($signature, 1, now()->addMinutes($minutes));
    }

    protected function sendThrottled(callable $send): void
    {
        $max = (int) $this->config('max_per_minute');

        if ($max <= 0) {
            $send();

            return;
        }

        $executed = RateLimiter::attempt('exception-mailer', $max, $send);

        if (! $executed) {
            Log::warning('exception-mailer: throttle reached, mail suppressed (max_per_minute='.$max.')');
        }
    }

    protected function sendRateLimitAlert(TooManyRequestsHttpException $e): void
    {
        $ip = request()->ip() ?? 'unknown';

        $minutes = (int) $this->config('rate_limit_alert.dedup_minutes');
        if ($minutes > 0) {
            $key = 'exception-mailer:429:'.md5($ip);
            if (! $this->cache()->add($key, 1, now()->addMinutes($minutes))) {
                return;
            }
        }

        $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

        $this->send(new RateLimitExceeded(
            ip: $ip,
            url: request()->fullUrl(),
            method: request()->method(),
            userAgent: request()->userAgent(),
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
        ));
    }

    protected function send(Mailable $mailable): void
    {
        $to = $this->addresses($this->config('to'));

        if (empty($to)) {
            Log::warning('exception-mailer: no recipient configured (exception-mailer.to)');

            return;
        }

        $pending = Mail::to($to);

        if ($cc = $this->addresses($this->config('cc'))) {
            $pending->cc($cc);
        }

        if ($bcc = $this->addresses($this->config('bcc'))) {
            $pending->bcc($bcc);
        }

        if ($this->config('queue')) {
            $pending->queue($mailable);
        } else {
            $pending->send($mailable);
        }
    }

    protected function isDontReport(Throwable $e): bool
    {
        foreach ((array) $this->config('dont_report') as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    protected function isBotRequest(): bool
    {
        $userAgent = request()->userAgent();

        if (! $userAgent) {
            return false;
        }

        $userAgent = strtolower($userAgent);

        foreach ((array) $this->config('bot_needles') as $needle) {
            if ($needle !== '' && str_contains($userAgent, strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function addresses(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', $parts)));
    }

    protected function cache(): CacheRepository
    {
        $store = $this->config('cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('exception-mailer.'.$key, $default);
    }

    protected function logQuietly(string $message, Throwable $e): void
    {
        try {
            Log::error($message, ['exception' => $e->getMessage()]);
        } catch (Throwable) {
            // Logging failed too — give up silently rather than recurse.
        }
    }
}
