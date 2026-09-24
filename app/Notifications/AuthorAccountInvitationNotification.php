<?php
// app/Notifications/AuthorAccountInvitationNotification.php

namespace App\Notifications;

use App\Models\AbstractAuthor;
use App\Models\AbstractSubmission;
use App\Support\AuthorActivationUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuthorAccountInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AbstractSubmission $abstract,
        private readonly AbstractAuthor $author,
    ) {
        $this->queue = 'emails';
        $this->tries = 3;
        $this->backoff = [30, 120, 300];
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activationUrl = AuthorActivationUrl::for($notifiable->id, 14);

        return (new MailMessage)
            ->subject("Access your ICW 2026 abstract — {$this->abstract->reference}")
            ->greeting("Hello {$this->author->name},")
            ->line("Your abstract \"{$this->abstract->title}\" ({$this->abstract->reference}) has been reviewed by the Scientific & Abstract Committee.")
            ->line("We've created an account for you so you can log in, read the reviewers' comments, and submit a corrected version as soon as possible.")
            ->action('Set your password & log in', $activationUrl)
            ->line('This link expires in 2 days.')
            ->line('If you were not the corresponding author for this abstract, please ignore this email.')
            ->salutation('— The ICW 2026 Scientific & Abstract Committee');
    }
}