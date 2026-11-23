<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Email verification</title></head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #263238; max-width: 600px; margin: 0 auto; padding: 24px; background: #F5F7FA;">
    <div style="background: #0D47A1; color: white; padding: 20px; border-radius: 12px 12px 0 0;">
        <h1 style="margin: 0; font-size: 18px;">SMART-DTR</h1>
        <p style="margin: 4px 0 0; opacity: 0.85; font-size: 13px;">Isabela National High School</p>
    </div>
    <div style="background: #FFFFFF; border: 1px solid rgba(13,71,161,0.12); border-top: none; padding: 24px; border-radius: 0 0 12px 12px;">
        <h2 style="margin: 0 0 12px; font-size: 16px; color: #263238;">Verify your email</h2>
        <p style="margin: 0 0 16px; color: #455A64;">Hi {{ $name }}, use this code to finish Sign Up. HR will only see your registration after your email is verified.</p>
        <p style="margin: 0 0 8px; font-size: 28px; letter-spacing: 0.35em; font-weight: 700; color: #0D47A1; text-align: center;">{{ $code }}</p>
        <p style="margin: 16px 0 0; color: #78909C; font-size: 13px; text-align: center;">This code expires in 15 minutes. If you did not register, ignore this email.</p>
    </div>
    <p style="text-align: center; font-size: 11px; color: #90A4AE; margin-top: 16px;">This is an automated message from SMART-DTR. Please do not reply.</p>
</body>
</html>
