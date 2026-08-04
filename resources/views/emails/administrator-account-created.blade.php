<!DOCTYPE html>
<html lang="en">
<body>
    <p>Hello {{ $administrator->name }},</p>

    <p>A {{ strtolower(ucwords(str_replace('_', ' ', $administrator->role))) }} account has been created for you at {{ config('app.name') }}.</p>

    <p><strong>Email:</strong> {{ $administrator->email }}<br>
    <strong>Temporary password:</strong> {{ $temporaryPassword }}</p>

    <p>You can sign in at <a href="{{ route('login') }}">{{ route('login') }}</a>. Please change your password after signing in.</p>
</body>
</html>
