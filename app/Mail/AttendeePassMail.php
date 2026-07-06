<?php

namespace App\Mail;

use App\Models\Attendee;
use App\Models\EventPass;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class AttendeePassMail extends Mailable
{
    use Queueable, SerializesModels;

    // public function __construct(
    //     public readonly Attendee $attendee,
    //     public readonly EventPass $pass,
    // ) {}

    public function __construct(
    public readonly Attendee $attendee,
    public readonly EventPass $pass,
    public readonly string $pdfContent, 
    public readonly ?string $plainPassword = null, 
) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Event Pass – ' . $this->pass->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.attendee-pass',
            with: [
                'attendee' => $this->attendee,
                'event'    => $this->pass->event,
                'pass'     => $this->pass,
                'plainPassword' => $this->plainPassword,
            ],
        );
    }

    public function attachments(): array
{
    return [
        Attachment::fromData(fn () => $this->pdfContent, 'event-pass-' . $this->pass->serialNumber . '.pdf')
            ->withMime('application/pdf'),
    ];
}
}