<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailAddress extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $previousRoot = URL::to('/');
        URL::forceRootUrl(config('app.url'));

        try {
            $url = URL::temporarySignedRoute(
                'email.verify',
                now()->addHour(),
                [
                    'user' => $notifiable->user_id,
                    'hash' => sha1($notifiable->email),
                ]
            );
        } finally {
            URL::forceRootUrl($previousRoot);
        }

        return (new MailMessage)
            ->subject('Verify your AIVO email address')
            ->line('Confirm that '.$notifiable->email.' belongs to you.')
            ->action('Verify email address', $url)
            ->line('This link expires in 60 minutes. If you did not request it, you can ignore this email.');
    }
}
