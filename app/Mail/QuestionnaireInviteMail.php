<?php
// app/Mail/QuestionnaireInviteMail.php
// Queued automatically. Run a queue worker (php artisan queue:work) or emails send inline on the "sync" driver.

namespace App\Mail;

use App\Models\Attendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuestionnaireInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Attendee $attendee,
        public string $url,
        public string $eventName,
        public bool $reminder = false,
    ) {}

    public function build()
    {
        return $this
            ->subject(($this->reminder ? 'Reminder: ' : '') . 'Your certificate is waiting: ' . $this->eventName)
            ->view('emails.questionnaire-invite');
    }
}