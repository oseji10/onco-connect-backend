<?php

namespace App\Notifications;

use App\Models\AbstractSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A free-text message to an abstract's corresponding author — used for
 * category broadcasts ("all oral presenters") and ad-hoc selected-abstract
 * sends (reclassification notices, logistics updates, etc.). Deliberately
 * separate from AbstractDecisionNotification: it never implies a status
 * change, and sending one does not touch decision_notified_at.
 */
class AbstractCustomNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AbstractSubmission $abstract,
        private readonly string $subject,
        private readonly string $body,
        private readonly ?string $authorName = null
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
        return (new MailMessage)
            ->subject($this->subject)
            ->theme('conference')
            ->markdown('emails.abstracts.custom-message', [
                'abstract' => $this->abstract,
                'authorName' => $this->authorName,
                'body' => $this->body,
            ]);
    }
}