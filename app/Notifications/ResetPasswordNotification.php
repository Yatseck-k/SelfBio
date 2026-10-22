<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** The letter with the link to a new password of the personal account (the default one is in English and has no site name). */
class ResetPasswordNotification extends ResetPassword
{
    #[\Override]
    public function toMail($notifiable): MailMessage
    {
        $brand = (string) config('app.name');
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject("Сброс пароля — {$brand}")
            ->greeting('Здравствуйте!')
            ->line("Для вашей учётной записи в личном кабинете «{$brand}» запросили сброс пароля.")
            ->action('Задать новый пароль', $this->resetUrl($notifiable))
            ->line("Ссылка действует {$minutes} минут и работает один раз.")
            ->line('Если вы не запрашивали сброс, ничего делать не нужно: пароль останется прежним.')
            ->salutation($brand);
    }
}
