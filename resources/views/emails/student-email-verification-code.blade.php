<!DOCTYPE html>
<html lang="en">
<body>
    <p>Hello {{ $student->name }},</p>
    <p>Use this one-time code to verify your email address for {{ config('app.name') }}:</p>
    <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px;">{{ $otp }}</p>
    <p>This code expires in 10 minutes. If you did not register, you can ignore this email.</p>
</body>
</html>
