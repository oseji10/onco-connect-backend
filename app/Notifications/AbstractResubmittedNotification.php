<?php
// app/Notifications/AbstractResubmittedNotification.php

namespace App\Notifications;

use App\Models\AbstractSubmission;
use App\Models\ReviewAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbstractResubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AbstractSubmission $abstract,
        private readonly string $reviewerName,
        private readonly ReviewAssignment $assignment,
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
        $note = $this->abstract->resubmission_note
            ? 'Author\'s note: "' . $this->abstract->resubmission_note . '"'
            : '';

        return (new MailMessage)
            ->subject("Resubmission awaiting review — {$this->abstract->reference}")
            ->greeting("Hello {$this->reviewerName},")
            ->line("The authors of \"{$this->abstract->title}\" have submitted a revised version ({$this->abstract->reference}).")
            ->line('This is a documentation review — no accept/reject decision is required. Please score the revised version so the committee has a record of the changes.')
            ->when($note, fn ($mail) => $mail->line($note))
            ->line('Your previous review is visible in your dashboard for reference.')
            ->salutation('— The ICW 2026 Abstract Committee');
    }
}