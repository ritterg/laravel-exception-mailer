<?php

use Illuminate\Support\Facades\Mail;
use Ritterg\ExceptionMailer\Facades\ExceptionMailer;
use Ritterg\ExceptionMailer\Mail\ExceptionOccurred;
use Ritterg\ExceptionMailer\Mail\RateLimitExceeded;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

beforeEach(function () {
    Mail::fake();
});

it('sends one exception mail', function () {
    ExceptionMailer::report(new RuntimeException('boom'));

    Mail::assertSent(ExceptionOccurred::class, 1);
});

it('renders the request url in the mail body', function () {
    ExceptionMailer::report(new RuntimeException('boom'));

    Mail::assertSent(ExceptionOccurred::class, function (ExceptionOccurred $mail) {
        $rendered = $mail->render();

        return str_contains($rendered, $mail->url)
            && str_contains($rendered, 'exception-url-banner');
    });
});

it('deduplicates identical exceptions within the window', function () {
    ExceptionMailer::report(new RuntimeException('boom'));
    ExceptionMailer::report(new RuntimeException('boom'));

    Mail::assertSent(ExceptionOccurred::class, 1);
});

it('sends separate mails for different messages', function () {
    ExceptionMailer::report(new RuntimeException('one'));
    ExceptionMailer::report(new RuntimeException('two'));

    Mail::assertSent(ExceptionOccurred::class, 2);
});

it('stops after the global throttle cap', function () {
    config()->set('exception-mailer.max_per_minute', 3);
    config()->set('exception-mailer.dedup_minutes', 0); // isolate the throttle

    foreach (range(1, 5) as $i) {
        ExceptionMailer::report(new RuntimeException('err '.$i));
    }

    Mail::assertSent(ExceptionOccurred::class, 3);
});

it('does not send when disabled', function () {
    config()->set('exception-mailer.enabled', false);

    ExceptionMailer::report(new RuntimeException('boom'));

    Mail::assertNothingSent();
});

it('does not send for dont_report types', function () {
    config()->set('exception-mailer.dont_report', [InvalidArgumentException::class]);

    ExceptionMailer::report(new InvalidArgumentException('nope'));

    Mail::assertNothingSent();
});

it('ignores bot user agents', function () {
    config()->set('exception-mailer.bot_needles', ['googlebot']);
    request()->headers->set('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)');

    ExceptionMailer::report(new RuntimeException('boom'));

    Mail::assertNothingSent();
});

it('does not send and does not throw when no recipient is configured', function () {
    config()->set('exception-mailer.to', null);

    ExceptionMailer::report(new RuntimeException('boom'));

    Mail::assertNothingSent();
});

it('sends a rate-limit alert when enabled', function () {
    config()->set('exception-mailer.rate_limit_alert.enabled', true);

    ExceptionMailer::report(new TooManyRequestsHttpException());

    Mail::assertSent(RateLimitExceeded::class, 1);
    Mail::assertNotSent(ExceptionOccurred::class);
});

it('deduplicates rate-limit alerts per IP', function () {
    config()->set('exception-mailer.rate_limit_alert.enabled', true);

    ExceptionMailer::report(new TooManyRequestsHttpException());
    ExceptionMailer::report(new TooManyRequestsHttpException());

    Mail::assertSent(RateLimitExceeded::class, 1);
});

it('sends nothing for a 429 when the alert is disabled', function () {
    config()->set('exception-mailer.rate_limit_alert.enabled', false);

    ExceptionMailer::report(new TooManyRequestsHttpException());

    Mail::assertNothingSent();
});

it('sends a test mail via the artisan command, bypassing the enabled flag', function () {
    config()->set('exception-mailer.enabled', false); // command must work regardless

    $this->artisan('exception-mailer:test')->assertSuccessful();

    Mail::assertSent(ExceptionOccurred::class, 1);
});

it('fails the test command when no recipient is configured', function () {
    config()->set('exception-mailer.to', null);

    $this->artisan('exception-mailer:test')->assertFailed();

    Mail::assertNothingSent();
});
