<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Description('Show how the site sends mail and, with an address, send a test letter')]
#[Signature('mail:check {to? : An address to send a test letter to}')]
class CheckMail extends Command
{
    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        $this->line("Почтовый драйвер: {$mailer}");
        $this->line("Отправитель: {$from} ({$this->laravel['config']->get('mail.from.name')})");

        $problems = [];
        // «RESEND_KEY=» in .env gives an empty string, not null.
        if ($mailer === 'resend' && blank(config('services.resend.key'))) {
            $problems[] = 'Для драйвера resend не задан RESEND_KEY в .env.';
        }
        if (in_array($mailer, ['log', 'array'], true)) {
            $problems[] = "Драйвер «{$mailer}» только пишет письма в журнал: наружу они не уходят.";
        }
        if ($mailer === 'smtp' && in_array(config('mail.mailers.smtp.host'), ['mailpit', '127.0.0.1', 'localhost'], true)) {
            $problems[] = 'SMTP указывает на локальную «ловушку» (mailpit): наружу письма не уходят.';
        }
        if (str_ends_with($from, '@local.test') || str_ends_with($from, '@example.com')) {
            $problems[] = "Адрес отправителя {$from} — заглушка: укажите адрес на подтверждённом домене (MAIL_FROM_ADDRESS).";
        }
        foreach ($problems as $problem) {
            $this->warn($problem);
        }

        $to = $this->argument('to');
        if ($to === null) {
            return $problems === [] ? self::SUCCESS : self::FAILURE;
        }

        try {
            Mail::raw('Проверка отправки почты с сайта '.config('app.name').'. Если вы читаете это письмо, почта настроена.', function ($message) use ($to): void {
                $message->to($to)->subject('Проверка почты — '.config('app.name'));
            });
        } catch (Throwable $e) {
            $this->error('Письмо не отправлено: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Письмо отправлено на {$to}.");

        return self::SUCCESS;
    }
}
