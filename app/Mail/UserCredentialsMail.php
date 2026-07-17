<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserCredentialsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $plainPassword,
    ) {
        // Set inside the constructor body, not as class properties — a
        // property-vs-trait conflict on $queue bit us on the abstract
        // module, so this pattern avoids repeating that bug.
        $this->queue = 'emails';
        $this->tries = 3;
        $this->backoff = [30, 120, 300];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your ICW 2026 Dashboard Login Credentials');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.user-credentials');
    }
}