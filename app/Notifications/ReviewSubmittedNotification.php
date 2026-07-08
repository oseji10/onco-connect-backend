<?php

namespace App\Notifications;

use App\Models\AbstractSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AbstractSubmission $abstract,
        private readonly int $reviewsSubmitted,
        private readonly int $reviewsAssigned
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
        // Deliberately doesn't reveal the score, comments, or reviewer
        // identity — that stays with the committee until a final decision
        // is made, to protect blind review.
        return (new MailMessage)
            ->subject("A review has been submitted for {$this->abstract->reference}")
            ->greeting('Hello,')
            ->line("Your abstract \"{$this->abstract->title}\" ({$this->abstract->reference}) has received a new review from the ICW 2026 Abstract Committee.")
            ->line("Reviews received so far: {$this->reviewsSubmitted} of {$this->reviewsAssigned}.")
            ->line('You will be notified separately once a final decision has been made.');
    }
}