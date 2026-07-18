<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $loginUrl;

    public function __construct(
        public User $user,
        public string $otp,
    ) {
        $this->queue = 'emails';
        $this->tries = 3;
        $this->backoff = [30, 120, 300];

        // FRONTEND_URL should point at your Next.js app's base, e.g.
        // https://icw.example.com — add it to .env if it isn't there yet.
        $base = rtrim(config('app.frontend_url', env('FRONTEND_URL', '')), '/');
        $this->loginUrl = $base . '/icw/login-otp?email=' . urlencode($user->email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your ICW 2026 Dashboard Login Code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.user-otp');
    }
}