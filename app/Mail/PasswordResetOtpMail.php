<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otp,
        public int $minutes = 15,
    ) {}

    public function build()
    {
        return $this->subject('Your password reset code')
            ->view('emails.password-reset-otp');
    }
}