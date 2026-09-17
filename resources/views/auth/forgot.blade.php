<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — OLSHCO EAS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="grid min-h-screen place-items-center p-5">
    <form method="post" action="{{ route('password.email') }}" class="w-full max-w-md rounded-3xl bg-white p-8 shadow-xl">
        @csrf
        <h1 class="text-2xl font-bold text-[#123A63]">Reset your password</h1>
        <p class="mb-6 mt-2 text-sm text-slate-500">We’ll send a reset link to your school email.</p>

        @if(session('success'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        <div>
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@olshco.edu.ph">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button class="mt-5 w-full rounded-xl bg-[#123A63] py-3 font-semibold text-white hover:bg-[#245B8E] transition">Send reset link</button>
        <a href="{{ route('login') }}" class="mt-4 block text-center text-sm text-[#245B8E]">Back to sign in</a>
    </form>
</body>
</html>

