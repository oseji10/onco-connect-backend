<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#1f2937; line-height:1.6; margin:0; padding:24px;">
    <p>Dear {{ trim(($attendee->title ? $attendee->title . ' ' : '') . $attendee->firstName) }},</p>

    <p>Please find attached your <strong>{{ $typeLabel }}</strong>.</p>

    <p>Thank you for being part of {{ $certificate->event->name ?? $certificate->event->title ?? 'the event' }}.</p>

    <p style="margin-top:24px;">Warm regards,<br>The Organising Team</p>

    <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

    <p style="font-size:12px; color:#9ca3af;">
        Certificate No: {{ $certificate->certificateNumber }}
    </p>
</body>
</html>