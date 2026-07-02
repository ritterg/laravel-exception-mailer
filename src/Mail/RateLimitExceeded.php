<?php

namespace Ritterg\ExceptionMailer\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Slim abuse alert sent when an IP trips a request rate limit (HTTP 429).
 * Intentionally lightweight — no stack trace, just who/what/where.
 */
class RateLimitExceeded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $ip,
        public string $url,
        public string $method,
        public ?string $userAgent = null,
        public ?int $retryAfter = null,
    ) {}

    public function build(): self
    {
        $subject = trim((string) config('exception-mailer.subject_prefix', config('app.name')))
            .': Rate limit exceeded | '.$this->ip;

        $mail = $this->subject($subject)
            ->view(config('exception-mailer.rate_limit_alert.view'), [
                'ip' => $this->ip,
                'url' => $this->url,
                'method' => $this->method,
                'userAgent' => $this->userAgent,
                'retryAfter' => $this->retryAfter,
            ]);

        if ($from = config('exception-mailer.from')) {
            $mail->from($from);
        }

        return $mail;
    }
}
