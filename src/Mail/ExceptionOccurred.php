<?php

namespace Ritterg\ExceptionMailer\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
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
    use Queueable, SerializesModels;

    public string $css;

    public string $content;

    public string $mailSubject;

    public function __construct(Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $renderer = new HtmlErrorRenderer(true);

        $this->css = $renderer->getStylesheet();
        $this->content = $renderer->getBody($flattened);

        $this->mailSubject = trim((string) config('exception-mailer.subject_prefix', config('app.name')))
            .': '.class_basename($exception)
            .' | '.config('app.env')
            .' | '.request()->fullUrl();
    }

    public function build(): self
    {
        $mail = $this->subject($this->mailSubject)
            ->view(config('exception-mailer.view'), [
                'css' => $this->css,
                'content' => $this->content,
            ]);

        if ($from = config('exception-mailer.from')) {
            $mail->from($from);
        }

        return $mail;
    }
}
