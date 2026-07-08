<?php

namespace App\Notifications;

use App\Models\Reviewer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewerInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Reviewer $reviewer)
    {
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
        $acceptUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            . '/abstracts/reviewer/accept-invite?token=' . $this->reviewer->invite_token;

        return (new MailMessage)
            ->subject('You are invited to review ICW 2026 abstracts')
            ->greeting("Hello {$this->reviewer->name},")
            ->line('The ICW 2026 Abstract Committee would like you to join as a reviewer.')
            ->line('You will be asked to score assigned abstracts on Significance, Relevance, and Originality.')
            ->action('Accept invitation', $acceptUrl)
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}