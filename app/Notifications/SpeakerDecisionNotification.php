<?php

namespace App\Notifications;

use App\Models\Speaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SpeakerDecisionNotification extends Notification implements ShouldQueue
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
        return $this->speaker->status === 'confirmed'
            ? $this->confirmedMessage()
            : $this->rejectedMessage();
    }

    private function confirmedMessage(): MailMessage
    {
        return (new MailMessage)
            ->subject("You're confirmed to speak at ICW 2026 — {$this->speaker->reference}")
            ->greeting('Congratulations!')
            ->line("Your session \"{$this->speaker->session_title}\" has been confirmed for the International Cancer Week 2026 Conference.")
            ->line("Session type: {$this->speaker->session_type} · Participation: {$this->speaker->participation_type}.")
            ->line('The organizing committee will follow up with scheduling details and speaker guidelines.');
    }

    private function rejectedMessage(): MailMessage
    {
        return (new MailMessage)
            ->subject("Update on your speaker registration — {$this->speaker->reference}")
            ->greeting('Hello,')
            ->line("Thank you for your interest in speaking at the International Cancer Week 2026 Conference with your proposed session \"{$this->speaker->session_title}\".")
            ->line('After review, the organizing committee is unable to accommodate this session this year.')
            ->line('We encourage you to submit again for future editions of International Cancer Week.');
    }
}