<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Password reset code</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:#1F6F43;padding:20px 28px;color:#ffffff;font-size:18px;font-weight:bold;">
                            Password reset
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 12px;font-size:15px;">
                                Hello {{ trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) ?: 'there' }},
                            </p>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">
                                Use this code to reset your password. It expires in {{ $minutes }} minutes.
                            </p>

                            <div style="text-align:center;margin:0 0 20px;">
                                <span style="display:inline-block;padding:14px 28px;border-radius:12px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;font-size:32px;font-weight:bold;letter-spacing:8px;">
                                    {{ $otp }}
                                </span>
                            </div>

                            <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
                                If you didn't ask to reset your password, you can ignore this email.
                                Your password won't change and nobody can use this code without it.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>