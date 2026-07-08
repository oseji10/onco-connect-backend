<?php

namespace App\Notifications;

use App\Models\AbstractSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbstractAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AbstractSubmission $abstract)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reviewUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            . '/';

        return (new MailMessage)
            ->subject('New ICW 2026 abstract assigned for review')
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been assigned to review: \"{$this->abstract->title}\".")
            ->action('Review now', $reviewUrl);
    }
}