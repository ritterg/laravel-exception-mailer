<?php

namespace Ritterg\ExceptionMailer\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Ritterg\ExceptionMailer\Mail\Concerns\TagsExceptionMail;

/**
 * Slim abuse alert sent when an IP trips a request rate limit (HTTP 429).
 * Intentionally lightweight — no stack trace, just who/what/where.
 */
class RateLimitExceeded extends Mailable
{
    use Queueable, SerializesModels, TagsExceptionMail;

    public function __construct(
        public string $ip,
        public string $url,
        public string $method,
        public ?string $userAgent = null,
        public ?int $retryAfter = null,
    ) {}

    public function build(): self
    {
        $this->addExceptionHeaders('rate-limit', [
            'Ip' => $this->ip,
            'Url' => $this->url,
        ]);

        $mail = $this->subject($this->taggedSubject('Rate limit exceeded | '.$this->ip))
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
