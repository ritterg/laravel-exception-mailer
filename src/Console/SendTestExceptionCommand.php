<?php

namespace Ritterg\ExceptionMailer\Console;

use Illuminate\Console\Command;
use Ritterg\ExceptionMailer\ExceptionMailer;
use Throwable;

class SendTestExceptionCommand extends Command
{
    protected $signature = 'exception-mailer:test';

    protected $description = 'Send a test exception e-mail to verify mail delivery and recipient configuration.';

    public function handle(ExceptionMailer $mailer): int
    {
        $this->info('Sending test exception mail...');

        try {
            $recipients = $mailer->sendTest();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent to: '.implode(', ', $recipients));
        $this->line('If it does not arrive, check your MAIL_* settings and the mail log.');

        return self::SUCCESS;
    }
}
