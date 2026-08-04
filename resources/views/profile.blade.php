@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="mx-auto max-w-5xl">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Account</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Your Profile</h1>
        <p class="mt-2 text-slate-500">Review your account information and keep your sign-in details secure.</p>
    </div>

    <div class="mt-7 grid gap-6 lg:grid-cols-[.8fr_1.2fr]">
        <aside class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid size-20 place-items-center rounded-2xl bg-[#123A63] text-3xl font-bold text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
            <h2 class="mt-5 text-xl font-bold text-slate-900">{{ $user->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $user->email }}</p>
            <span class="mt-4 inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-[#245B8E]">{{ str_replace('_', ' ', $user->role) }}</span>

            <dl class="mt-6 divide-y divide-slate-100 border-t border-slate-100 text-sm">
                <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Account status</dt><dd class="font-semibold text-emerald-700">{{ $user->is_active ? 'Active' : 'Disabled' }}</dd></div>
                @if($user->student)
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Student number</dt><dd class="text-right font-semibold text-slate-800">{{ $user->student->student_number }}</dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Student status</dt><dd class="text-right font-semibold text-slate-800">{{ ucfirst($user->student->student_type ?? 'regular') }}</dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Course</dt><dd class="text-right font-semibold text-slate-800">{{ $user->student->course?->code ?? 'Not assigned' }}</dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Year and section</dt><dd class="text-right font-semibold text-slate-800">Year {{ $user->student->year_level }}{{ $user->student->section ? ' – '.$user->student->section->name : '' }}</dd></div>
                @elseif($user->faculty)
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Employee number</dt><dd class="text-right font-semibold text-slate-800">{{ $user->faculty->employee_number ?: 'Not assigned' }}</dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Designation</dt><dd class="text-right font-semibold text-slate-800">{{ $user->faculty->designation }}</dd></div>
                @endif
                <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Member since</dt><dd class="text-right font-semibold text-slate-800">{{ $user->created_at->format('F Y') }}</dd></div>
            </dl>
        </aside>

        <div class="space-y-6">
            <form method="post" action="{{ route('profile.update') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf @method('PUT')
                <h2 class="text-lg font-bold text-slate-900">Personal details</h2>
                <p class="mt-1 text-sm text-slate-500">Update the name and email address used by your account.</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><label for="name">Full name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">@error('name', 'details')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email">@error('email', 'details')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                </div>
                <div class="mt-5 flex justify-end border-t pt-5"><button class="rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white hover:bg-[#0d2d4e]">Save Details</button></div>
            </form>

            <form method="post" action="{{ route('profile.password.update') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf @method('PUT')
                <h2 class="text-lg font-bold text-slate-900">Change password</h2>
                <p class="mt-1 text-sm text-slate-500">Use at least eight characters for your new password.</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label for="current_password">Current password</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password">@error('current_password', 'password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="password">New password</label><input id="password" type="password" name="password" required autocomplete="new-password">@error('password', 'password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
                </div>
                <div class="mt-5 flex justify-end border-t pt-5"><button class="rounded-xl bg-[#B3262E] px-5 py-3 font-semibold text-white hover:bg-red-800">Change Password</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
