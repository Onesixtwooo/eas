@extends('layouts.app')

@section('title', 'Edit User Account')

@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('admin.accounts.index') }}" class="text-sm font-semibold text-[#245B8E]">← Back to user accounts</a>
    <div class="mt-4">
        <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Account</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Edit User Account</h1>
        <p class="mt-2 text-slate-500">Update sign-in details, access role, status, or password.</p>
    </div>

    <form method="post" action="{{ route('admin.accounts.update', $account) }}" class="mt-7 space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <div><label for="name">Full name</label><input id="name" name="name" value="{{ old('name', $account->name) }}" required>@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $account->email) }}" required>@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="role">Role</label><select id="role" name="role" required>@foreach(['admin' => 'Admin', 'program_head' => 'Program Head', 'faculty' => 'Faculty', 'student' => 'Student'] as $value => $label)<option value="{{ $value }}" @selected(old('role', $account->role) === $value)>{{ $label }}</option>@endforeach</select>@error('role')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="is_active">Login access</label><select id="is_active" name="is_active" required><option value="1" @selected((string) old('is_active', (int) $account->is_active) === '1')>Can log in</option><option value="0" @selected((string) old('is_active', (int) $account->is_active) === '0')>Disabled</option></select>@error('is_active')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="password">New password</label><input id="password" type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password">@error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"></div>
        </div>

        <div class="flex justify-end gap-3 border-t pt-5">
            <a href="{{ route('admin.accounts.index') }}" class="rounded-xl border px-5 py-3 font-semibold text-slate-700">Cancel</a>
            <button class="rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white">Save Changes</button>
        </div>
    </form>
</div>
@endsection
