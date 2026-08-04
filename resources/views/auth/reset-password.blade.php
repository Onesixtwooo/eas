<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set New Password — OLSHCO EAS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="grid min-h-screen place-items-center p-5">
    <form method="post" action="{{ route('password.update') }}" class="w-full max-w-md rounded-3xl bg-white p-8 shadow-xl">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <h1 class="text-2xl font-bold text-[#123A63]">Set a new password</h1>
        <p class="mb-6 mt-2 text-sm text-slate-500">Enter your account email and choose a new password.</p>
        <div>
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autofocus>
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="mt-4">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="mt-4">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button class="mt-6 w-full rounded-xl bg-[#123A63] py-3 font-semibold text-white hover:bg-[#245B8E]">Reset Password</button>
        <a href="{{ route('login') }}" class="mt-4 block text-center text-sm text-[#245B8E]">Back to sign in</a>
    </form>
</body>
</html>
