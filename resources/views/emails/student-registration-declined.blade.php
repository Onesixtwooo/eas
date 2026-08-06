<!DOCTYPE html>
<html lang="en">
<body>
    <p>Hello {{ $student->name }},</p>

    <p>We are sorry to inform you that your student registration for {{ config('app.name') }} was declined.</p>

    <p><strong>Reason:</strong><br>{{ $student->registration_decline_reason }}</p>

    <p>If you believe this decision was made in error or need help correcting your registration, please contact the school administration.</p>
</body>
</html>
