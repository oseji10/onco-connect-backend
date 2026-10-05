<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Free-text message to a participant, with optional attachments.
 *
 * Queued, so the worker reads the attachment files AFTER the request ends.
 * Do not delete them in the controller; clear storage/app/mail-attachments
 * with a scheduled command instead (e.g. files older than 7 days).
 *
 * @param array<int, array{path:string, name:string, mime:?string}> $attachments
 */
class AttendeeCustomNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $recipientName,
        public array $attachments = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subjectLine)
            ->greeting("Dear {$this->recipientName},");

        // Blank line = new paragraph; single newlines are kept as <br>.
        foreach (preg_split('/\R{2,}/', trim($this->body)) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph !== '') {
                $mail->line(new HtmlString(nl2br(e($paragraph))));
            }
        }

        foreach ($this->attachments as $file) {
            $fullPath = Storage::disk('local')->path($file['path']);

            if (is_file($fullPath)) {
                $mail->attach($fullPath, [
                    'as' => $file['name'],
                    'mime' => $file['mime'] ?? 'application/octet-stream',
                ]);
            }
        }

        return $mail;
    }
}