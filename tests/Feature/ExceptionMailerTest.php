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

it('starts the subject with the fixed tag', function () {
    config()->set('exception-mailer.subject_prefix', 'My App');

    ExceptionMailer::report(new RuntimeException('boom'));

    Mail::assertSent(ExceptionOccurred::class, function (ExceptionOccurred $mail) {
        return str_starts_with($mail->mailSubject, '[Exception] My App: RuntimeException | ');
    });
});

it('tags the subject even when a published config lacks the key', function () {
    $config = config('exception-mailer');
    unset($config['subject_tag']);
    config()->set('exception-mailer', $config);

    ExceptionMailer::report(new RuntimeException('boom'));

    Mail::assertSent(ExceptionOccurred::class, fn (ExceptionOccurred $mail) => str_starts_with($mail->mailSubject, '[Exception] '));
});

it('omits the tag when configured empty', function () {
    config()->set('exception-mailer.subject_tag', '');
    config()->set('exception-mailer.subject_prefix', 'My App');

    ExceptionMailer::report(new RuntimeException('boom'));

    Mail::assertSent(ExceptionOccurred::class, fn (ExceptionOccurred $mail) => str_starts_with($mail->mailSubject, 'My App: '));
});

it('adds X-Exception headers once', function () {
    $mail = new ExceptionOccurred(new RuntimeException("multi\nline message"));
    $mail->render(); // build() runs here and again on send

    $message = new Symfony\Component\Mime\Email();
    $mail->build();
    foreach ((fn () => $this->callbacks)->call($mail) as $callback) {
        $callback($message);
    }
    $headers = $message->getHeaders();

    expect(iterator_to_array($headers->all('X-Exception-Mailer')))->toHaveCount(1)
        ->and($headers->get('X-Exception-Mailer')->getBodyAsString())->toBe('exception')
        ->and($headers->get('X-Exception-Class')->getBodyAsString())->toBe('RuntimeException')
        ->and($headers->get('X-Exception-Message')->getBodyAsString())->toBe('multi line message')
        ->and($headers->get('X-Exception-Fingerprint')->getBodyAsString())->toBe($mail->fingerprint)
        // Testbench's base_path() lives inside vendor/, so the test file stays absolute here.
        ->and($mail->location)->toContain('tests/Feature/ExceptionMailerTest.php:');
});

it('locates the first frame outside vendor', function () {
    // Created via a closure so the first trace frame is this test file.
    $make = fn () => new RuntimeException('x');
    $exception = $make();
    $reflection = new ReflectionProperty(Exception::class, 'file');
    $reflection->setValue($exception, '/srv/app/vendor/laravel/framework/Foo.php');

    expect(ExceptionOccurred::appLocation($exception))
        ->toContain('tests/Feature/ExceptionMailerTest.php:')
        ->not->toContain('/vendor/laravel/');
});

it('tags the rate-limit alert subject', function () {
    config()->set('exception-mailer.rate_limit_alert.enabled', true);

    ExceptionMailer::report(new TooManyRequestsHttpException());

    Mail::assertSent(RateLimitExceeded::class, fn (RateLimitExceeded $mail) => str_starts_with($mail->build()->subject, '[Exception] '));
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
