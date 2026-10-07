<?php

namespace App\Mail;

use App\Models\Attendee;
use App\Models\EventPass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class AttendeePassMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly Attendee $attendee,
        public readonly EventPass $pass,
        public readonly string $pdfPath,
        public readonly ?string $plainPassword = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Event Pass – ' . $this->pass->event->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.attendee-pass',
            with: [
                'attendee' => $this->attendee,
                'event' => $this->pass->event,
                'pass' => $this->pass,
                'plainPassword' => $this->plainPassword,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $absolutePath = Storage::disk('local')->path($this->pdfPath);

        return [
            Attachment::fromPath($absolutePath)
                ->as(
                    'event-pass-' . $this->pass->serialNumber . '.pdf'
                )
                ->withMime('application/pdf'),
        ];
    }
}

