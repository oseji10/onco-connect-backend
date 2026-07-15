<?php

namespace App\Notifications;

use App\Models\Speaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SpeakerSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Speaker $speaker)
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
            ->subject("Speaker registration received — {$this->speaker->reference}")
            ->greeting("Thank you, {$this->speaker->title} {$this->speaker->last_name}!")
            ->line("We've received your speaker registration for the International Cancer Week 2026 Conference.")
            ->line("Proposed session: \"{$this->speaker->session_title}\" ({$this->speaker->session_type}).")
            ->line("Your tracking reference is: {$this->speaker->reference}")
            ->line('The organizing committee will review your submission and follow up by email once a decision has been made.');
    }
}