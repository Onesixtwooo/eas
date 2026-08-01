<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — OLSHCO EAS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="min-h-screen bg-[#123A63]">
<main class="grid min-h-screen lg:grid-cols-2">
    <section class="hidden flex-col justify-between p-14 text-white lg:flex">
        <div class="flex items-center gap-3"><div class="grid size-12 place-items-center rounded-xl bg-white text-xl font-black text-[#123A63]">O</div><b>OLSHCO</b></div>
        <div class="max-w-xl"><p class="mb-4 text-sm font-semibold uppercase tracking-[.25em] text-blue-200">BS Information Technology</p><h1 class="text-5xl font-bold leading-tight">Excuse & Admission Slip Management System</h1><p class="mt-6 text-lg leading-relaxed text-blue-100">A secure and streamlined academic portal for submitting, reviewing, and verifying excuse requests.</p></div>
        <p class="text-sm text-blue-200">Our Lady of the Sacred Heart College of Guimba, Inc.</p>
    </section>
    <section class="flex items-center justify-center bg-[#F5F7FA] p-6">
        <div class="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl">
            <div class="mb-8 lg:hidden"><b class="text-xl text-[#123A63]">OLSHCO EAS</b></div>
            <h2 class="text-3xl font-bold text-slate-900">Welcome back</h2><p class="mt-2 text-slate-500">Sign in to access your portal.</p>
            <form method="post" action="{{ route('login.attempt') }}" class="mt-8 space-y-5">
                @csrf
                <div><label>Email address</label><input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@olshco.edu.ph">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><div class="flex justify-between"><label>Password</label><a class="text-sm text-[#245B8E]" href="{{ route('password.request') }}">Forgot password?</a></div><input type="password" name="password" required placeholder="••••••••"></div>
                <label class="flex items-center gap-2 text-sm font-normal"><input class="size-4 w-auto" type="checkbox" name="remember"> Keep me signed in</label>
                <button class="w-full rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white hover:bg-[#245B8E]">Sign in</button>
            </form>
            <div class="mt-6 border-t border-slate-200 pt-6">
                <p class="mb-3 text-center text-sm text-slate-500">New student?</p>
                <a href="{{ route('register', [], false) }}" class="block w-full rounded-xl border-2 border-[#123A63] px-5 py-3 text-center font-semibold text-[#123A63] transition hover:bg-[#123A63] hover:text-white focus:ring-4 focus:ring-blue-200">
                    Register Student Account
                </a>
            </div>
        </div>
    </section>
</main>
</body>
</html>
