{{-- Save as resources/views/emails/questionnaire-invite.blade.php --}}
<!DOCTYPE html>
<html>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;">
    <h2 style="margin:0 0 16px;color:#14532d;">
      {{ $reminder ? 'A quick reminder' : 'Thank you for joining us' }}
    </h2>

    <p>Dear {{ $attendee->firstName }},</p>

    <p>
      Thank you for being part of <strong>{{ $eventName }}</strong>.
      Please take two minutes to complete a short questionnaire. Once you submit it,
      your certificate of participation is unlocked and you can download it straight away.
    </p>

    <p style="text-align:center;margin:28px 0;">
      <a href="{{ $url }}"
         style="background:#16a34a;color:#ffffff;text-decoration:none;font-weight:bold;padding:14px 28px;border-radius:12px;display:inline-block;">
        Complete questionnaire
      </a>
    </p>

    <p style="font-size:12px;color:#6b7280;">
      No login is needed. This link is personal to you, so please do not share it.<br>
      If the button does not work, copy this address into your browser:<br>
      <span style="word-break:break-all;">{{ $url }}</span>
    </p>
  </div>
</body>
</html>