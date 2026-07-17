<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h2 style="color: #16a34a;">Welcome to the ICW 2026 Dashboard</h2>

    <p>Hi {{ $user->firstName }},</p>

    <p>An account has been created for you on the ICW 2026 admin dashboard. Your login details are below:</p>

    <table style="border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding: 6px 12px; font-weight: bold;">Email</td>
            <td style="padding: 6px 12px;">{{ $user->email }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; font-weight: bold;">Temporary Password</td>
            <td style="padding: 6px 12px;">{{ $plainPassword }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; font-weight: bold;">Role</td>
            <td style="padding: 6px 12px;">{{ $user->user_role?->roleName }}</td>
        </tr>
    </table>

    <p>Please log in and change your password as soon as possible.</p>

    <p>— ICW 2026 Team</p>
</body>
</html>