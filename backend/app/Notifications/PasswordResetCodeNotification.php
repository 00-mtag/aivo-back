<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your AIVO password reset code')
            ->line('Your AIVO password reset code is '.$this->code.'. It expires in '.config('auth.passwords.users.expire').' minutes.')
            ->line('If you did not request a password reset, you can ignore this email.');
    }
}
