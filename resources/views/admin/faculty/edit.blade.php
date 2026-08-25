@extends('layouts.app')
@section('title', 'Edit Faculty')
@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('admin.faculty.index') }}" class="text-sm font-semibold text-[#245B8E]">&larr; Back to faculty</a>
    <h1 class="mt-4 text-3xl font-bold text-slate-900">Edit Faculty</h1>
    <p class="mt-2 text-slate-500">Update the faculty profile{{ $faculty->user_id ? ' and portal account' : '' }}.</p>

    <form method="post" action="{{ route('admin.faculty.update', $faculty) }}" class="mt-7 space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @method('PUT')
        @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><b>Please correct the highlighted information.</b></div>@endif
        <div class="grid gap-5 sm:grid-cols-2">
            <div><label for="name">Full name *</label><input id="name" name="name" value="{{ old('name', $faculty->display_name) }}" required autofocus>@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="employee_number">Employee number</label><input id="employee_number" name="employee_number" value="{{ old('employee_number', $faculty->employee_number) }}">@error('employee_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2"><label for="designation">Designation *</label><input id="designation" name="designation" value="{{ old('designation', $faculty->designation) }}" required>@error('designation')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        </div>

        @if($faculty->user_id)
            <div class="border-t pt-6">
                <h2 class="font-bold text-[#123A63]">Portal Account</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><label for="email">Email address *</label><input id="email" type="email" name="email" value="{{ old('email', $faculty->user->email) }}" required>@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="is_active">Login status *</label><select id="is_active" name="is_active" required><option value="1" @selected((string) old('is_active', (int) $faculty->user->is_active) === '1')>Active</option><option value="0" @selected((string) old('is_active', (int) $faculty->user->is_active) === '0')>Disabled</option></select>@error('is_active')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="password">New password</label><input id="password" type="password" name="password" minlength="8" autocomplete="new-password"><p class="mt-1 text-xs text-slate-500">Leave blank to keep the current password.</p>@error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password"></div>
                </div>
            </div>
        @else
            <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">This instructor does not have a login account. Save profile changes here, or <a href="{{ route('admin.faculty.create', ['faculty_id' => $faculty->id]) }}" class="font-bold underline">create an account</a>.</div>
        @endif

        <div class="flex justify-end gap-3 border-t pt-5"><a href="{{ route('admin.faculty.index') }}" class="rounded-xl border px-5 py-3 font-semibold">Cancel</a><button class="rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white">Save Changes</button></div>
    </form>
</div>
@endsection
