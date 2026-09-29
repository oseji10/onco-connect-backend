<?php

namespace App\Notifications;

use App\Models\AbstractSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * A free-text message to an abstract's corresponding author — used for
 * category broadcasts ("all oral presenters") and ad-hoc selected-abstract
 * sends (reclassification notices, logistics updates, etc.). Deliberately
 * separate from AbstractDecisionNotification: it never implies a status
 * change, and sending one does not touch decision_notified_at.
 *
 * Optional file attachments are passed in as references to files already
 * stored on the "local" disk (see AbstractRankingController::sendCustom).
 * Because this notification is queued, those files must still exist when
 * the worker runs (including retries) — they are cleaned up by a
 * scheduled task, not right after the request.
 */
class AbstractCustomNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{path:string, name:string, mime:?string}>  $attachments
     */
    public function __construct(
        private readonly AbstractSubmission $abstract,
        private readonly string $subject,
        private readonly string $body,
        private readonly ?string $authorName = null,
        private readonly array $attachments = []
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
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->theme('conference')
            ->markdown('emails.abstracts.custom-message', [
                'abstract' => $this->abstract,
                'authorName' => $this->authorName,
                'body' => $this->body,
            ]);

        foreach ($this->attachments as $file) {
            $mail->attach(
                Storage::disk('local')->path($file['path']),
                [
                    'as' => $file['name'],
                    'mime' => $file['mime'] ?: 'application/octet-stream',
                ]
            );
        }

        return $mail;
    }
}