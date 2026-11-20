<?php

namespace Ritterg\ExceptionMailer\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Ritterg\ExceptionMailer\Mail\Concerns\TagsExceptionMail;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Throwable;

/**
 * Renders the full Symfony debug page (stack trace, source context, request
 * details) as the e-mail body. The HTML is built in the constructor so the
 * mailable stays fully serializable when queued.
 */
class ExceptionOccurred extends Mailable
{
    use Queueable, SerializesModels, TagsExceptionMail;

    public string $css;

    public string $content;

    public string $mailSubject;

    public string $url;

    public string $method;

    public string $exceptionClass;

    public string $exceptionMessage;

    /** First frame outside vendor/, relative to the project root ("app/Foo.php:42"). */
    public string $location;

    /** Stable id for grouping the same error across mails (class + location). */
    public string $fingerprint;

    public function __construct(Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $renderer = new HtmlErrorRenderer(true);

        $this->css = $renderer->getStylesheet();
        $this->content = $renderer->getBody($flattened);

        $this->url = request()->fullUrl();
        $this->method = request()->method();

        $this->exceptionClass = get_class($exception);
        $this->exceptionMessage = $exception->getMessage();
        $this->location = self::appLocation($exception);
        $this->fingerprint = substr(sha1($this->exceptionClass.'|'.$this->location), 0, 12);

        $this->mailSubject = $this->taggedSubject(
            class_basename($exception).' | '.config('app.env').' | '.$this->url
        );
    }

    /**
     * Where the error surfaced in the project's own code: the throw site if it
     * is outside vendor/, otherwise the first non-vendor frame of the trace.
     */
    public static function appLocation(Throwable $exception): string
    {
        $frames = array_merge(
            [['file' => $exception->getFile(), 'line' => $exception->getLine()]],
            $exception->getTrace()
        );

        $fallback = null;
        foreach ($frames as $frame) {
            if (! isset($frame['file'])) {
                continue;
            }
            $fallback ??= $frame;
            if (! str_contains($frame['file'], DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                return self::relativePath($frame['file']).':'.($frame['line'] ?? 0);
            }
        }

        return $fallback ? self::relativePath($fallback['file']).':'.($fallback['line'] ?? 0) : 'unknown';
    }

    private static function relativePath(string $file): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }

    public function build(): self
    {
        $this->addExceptionHeaders('exception', [
            'Class' => $this->exceptionClass,
            'Location' => $this->location,
            'Fingerprint' => $this->fingerprint,
            'Message' => $this->exceptionMessage,
            'Url' => $this->url,
        ]);

        $mail = $this->subject($this->mailSubject)
            ->view(config('exception-mailer.view'), [
                'css' => $this->css,
                'content' => $this->content,
                'url' => $this->url,
                'method' => $this->method,
            ]);

        if ($from = config('exception-mailer.from')) {
            $mail->from($from);
        }

        return $mail;
    }
}
