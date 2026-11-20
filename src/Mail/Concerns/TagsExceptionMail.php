<?php

namespace Ritterg\ExceptionMailer\Mail\Concerns;

use Symfony\Component\Mime\Email;

/**
 * Shared subject tagging and X-Exception-* headers, so every mail this
 * package sends can be caught by one mail rule or one IMAP header search.
 */
trait TagsExceptionMail
{
    private bool $exceptionHeadersAdded = false;

    /**
     * Build "[Exception] My App: <rest>". The default is applied here (not only
     * in the shipped config) so projects with a published config get the tag too.
     */
    protected function taggedSubject(string $rest): string
    {
        $tag = trim((string) config('exception-mailer.subject_tag', '[Exception]'));
        $prefix = trim((string) config('exception-mailer.subject_prefix', config('app.name')));

        return ltrim($tag.' '.$prefix.': '.$rest);
    }

    /**
     * @param  array<string, string|null>  $headers  name suffix => value
     */
    protected function addExceptionHeaders(string $kind, array $headers): void
    {
        // build() can run more than once (render + send); never add headers twice.
        if ($this->exceptionHeadersAdded) {
            return;
        }
        $this->exceptionHeadersAdded = true;

        $headers = ['Mailer' => $kind] + [
            'App' => (string) config('app.name'),
            'Env' => (string) config('app.env'),
        ] + $headers;

        $this->withSymfonyMessage(function (Email $message) use ($headers) {
            foreach ($headers as $name => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                // Headers must be single-line; keep them short.
                $value = mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $value)), 0, 300, '…');
                $message->getHeaders()->addTextHeader('X-Exception-'.$name, $value);
            }
        });
    }
}
