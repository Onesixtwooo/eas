<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Email — OLSHCO EAS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="min-h-screen bg-[#F5F7FA]">
<main class="flex min-h-screen items-center justify-center p-5">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-xl">
        <h1 class="text-3xl font-bold text-slate-900">Verify your email</h1>
        <p class="mt-3 text-slate-500">Enter the six-digit code sent to <strong>{{ $email }}</strong>. The code expires in 10 minutes.</p>
        @if(session('success'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif
        <form method="post" action="{{ route('register.verify-email.store') }}" class="mt-6">
            @csrf
            <label for="otp">Verification code</label>
            <input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus class="mt-2 text-center text-2xl tracking-[.35em]">
            @error('otp')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <button class="mt-5 w-full rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white">Verify Email</button>
        </form>
        <form method="post" action="{{ route('register.verify-email.resend') }}" class="mt-4 text-center">
            @csrf
            <button class="text-sm font-semibold text-[#245B8E] hover:underline">Send a new code</button>
        </form>
    </div>
</main>
</body>
</html>
