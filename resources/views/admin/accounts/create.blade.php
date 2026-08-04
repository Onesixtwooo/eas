@extends('layouts.app')

@section('title', 'Add Administrative Account')

@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('admin.accounts.index') }}" class="text-sm font-semibold text-[#245B8E]">← Back to user accounts</a>
    <div class="mt-4">
        <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Account</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Add Administrative Account</h1>
        <p class="mt-2 text-slate-500">Create an active administrator or program head account. A generated password will be emailed to the account holder.</p>
    </div>

    <form method="post" action="{{ route('admin.accounts.store') }}" class="mt-7 space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <div><label for="name">Full name</label><input id="name" name="name" value="{{ old('name') }}" required autofocus>@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" required>@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2"><label for="role">Role</label><select id="role" name="role" required><option value="admin" @selected(old('role', 'admin') === 'admin')>Administrator</option><option value="program_head" @selected(old('role') === 'program_head')>Program Head</option></select>@error('role')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        </div>
        <div class="rounded-xl bg-blue-50 p-4 text-sm text-blue-800"><b>Access:</b> Full administrative access &nbsp; <b>Login:</b> Active</div>
        <div class="flex justify-end gap-3 border-t pt-5">
            <a href="{{ route('admin.accounts.index') }}" class="rounded-xl border px-5 py-3 font-semibold text-slate-700">Cancel</a>
            <button class="rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white">Create Account</button>
        </div>
    </form>
</div>
@endsection
