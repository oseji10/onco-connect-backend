<?php

namespace App\Mail;

use App\Models\Attendee;
use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Attendee $attendee,
        public Certificate $certificate,
        public string $pdfContent,
        public string $typeLabel,
    ) {}

    public function build(): self
    {
        $filename = $this->certificate->certificateNumber . '.pdf';

        return $this->subject('Your ' . $this->typeLabel)
            ->view('emails.certificate')
            ->attachData($this->pdfContent, $filename, ['mime' => 'application/pdf']);
    }
}