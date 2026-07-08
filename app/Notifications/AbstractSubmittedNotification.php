<?php

namespace App\Notifications;

use App\Models\AbstractSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbstractSubmittedNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject("Abstract received — {$this->abstract->reference}")
            ->greeting('Thank you for your submission!')
            ->line("We've received your abstract \"{$this->abstract->title}\" for the 2026 International Cancer Week Conference.")
            ->line("Your tracking reference is: {$this->abstract->reference}")
            ->line("Presentation preference: {$this->abstract->presentation_type}.")
            ->line('The Abstract Committee will assign reviewers shortly. You\'ll receive an email each time a review comes in, and again once a final decision is made.')
            ->line('Please keep your reference number for any correspondence with the Abstract Committee.');
    }
}