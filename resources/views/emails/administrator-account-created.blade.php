<!DOCTYPE html>
<html lang="en">
<body>
    <p>Hello {{ $administrator->name }},</p>

    <p>A {{ strtolower(ucwords(str_replace('_', ' ', $administrator->role))) }} account has been created for you at {{ config('app.name') }}.</p>

    <p><strong>Email:</strong> {{ $administrator->email }}</p>

    <p><a href="{{ $setupUrl }}">Set your password</a> to activate your sign-in. This link expires in one hour. If it expires, request a new link from the sign-in page.</p>
</body>
</html>
