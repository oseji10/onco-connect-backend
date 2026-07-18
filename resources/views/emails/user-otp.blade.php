<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h2 style="color: #16a34a;">Welcome to the ICW 2026 Dashboard</h2>

    <p>Hi {{ $user->firstName }},</p>

    <p>An account has been created for you on the ICW 2026 admin dashboard. Use the one-time code
    below to log in for the first time — you'll be asked to set your own password right after.</p>

    <p style="font-size: 28px; font-weight: bold; letter-spacing: 4px; margin: 24px 0; text-align: center; background: #f0fdf4; padding: 16px; border-radius: 12px; color: #16a34a;">
        {{ $otp }}
    </p>

    <p>This code expires in 24 hours and can only be used once. If you didn't expect this email,
    you can ignore it.</p>

    <p>— ICW 2026 Team</p>
</body>
</html>