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

    /**
     * @param string      $status           'accepted' | 'rejected'
     * @param string|null $presentationType 'oral' | 'poster' — only meaningful when $status === 'accepted'
     */
    public function __construct(
        private readonly AbstractSubmission $abstract,
        private readonly string $status,
        private readonly ?string $presentationType = null,
        private readonly ?int $rank = null,
        private readonly ?string $subTheme = null,
        private readonly ?int $subThemeRank = null,
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

    /**
     * Three genuinely different emails, not one template with an if/else:
     *   - oral-accepted.blade.php    (top 30 / sub-theme top 5)
     *   - poster-accepted.blade.php  (everyone else scoring >= 2.5)
     *   - rejected.blade.php         (declined)
     * All three share the "conference" mail theme for consistent, branded
     * styling (see resources/views/vendor/mail/html/themes/conference.css).
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectFor())
            ->theme('conference')
            ->markdown($this->viewFor(), [
                'abstract' => $this->abstract,
                'authorName' => $this->authorName,
                'rank' => $this->rank,
                'subTheme' => $this->subTheme,
                'subThemeRank' => $this->subThemeRank,
                'presentationType' => $this->presentationType,
            ]);
    }

    private function viewFor(): string
    {
        if ($this->status === 'accepted' && $this->presentationType === 'poster') {
            return 'emails.abstracts.poster-accepted';
        }

        if ($this->status === 'accepted') {
            return 'emails.abstracts.oral-accepted';
        }

        return 'emails.abstracts.rejected';
    }

    private function subjectFor(): string
    {
        return $this->status === 'accepted'
            ? "Your abstract has been accepted — {$this->abstract->reference}"
            : "Update on your abstract submission — {$this->abstract->reference}";
    }
}