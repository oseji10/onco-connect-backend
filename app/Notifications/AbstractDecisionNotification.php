<?php

namespace App\Notifications;

use App\Models\AbstractSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbstractDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AbstractSubmission $abstract)
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
        return $this->abstract->status === 'accepted'
            ? $this->acceptedMessage()
            : $this->rejectedMessage();
    }

    private function acceptedMessage(): MailMessage
    {
        return (new MailMessage)
            ->subject("Your abstract has been accepted — {$this->abstract->reference}")
            ->greeting('Congratulations!')
            ->line("Your abstract \"{$this->abstract->title}\" ({$this->abstract->reference}) has been accepted for the International Cancer Week 2026 Conference.")
            ->line("Presentation format: {$this->abstract->presentation_type}.")
            ->line('Further details on scheduling and presentation guidelines will follow from the Abstract Committee.');
    }

    private function rejectedMessage(): MailMessage
    {
        return (new MailMessage)
            ->subject("Update on your abstract submission — {$this->abstract->reference}")
            ->greeting('Hello,')
            ->line("Thank you for submitting \"{$this->abstract->title}\" ({$this->abstract->reference}) to the International Cancer Week 2026 Conference.")
            ->line('After review, the Abstract Committee is unable to accept this abstract for presentation this year.')
            ->line('We encourage you to submit to future editions of International Cancer Week.');
    }
}