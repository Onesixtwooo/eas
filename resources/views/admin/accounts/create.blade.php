@extends('layouts.app')

@section('title', 'Add Account')

@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('admin.accounts.index') }}" class="text-sm font-semibold text-[#245B8E]">← Back to user accounts</a>
    <div class="mt-4">
        <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Account</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Add Account</h1>
        <p class="mt-2 text-slate-500">Create an administrative, faculty, adviser, or dual-role account. A generated password will be emailed to the account holder.</p>
    </div>

    <form method="post" action="{{ route('admin.accounts.store') }}" class="mt-7 space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <div><label for="name">Full name</label><input id="name" name="name" value="{{ old('name') }}" required autofocus>@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" required>@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <fieldset class="sm:col-span-2"><legend class="font-semibold">Roles</legend><p class="mt-1 text-xs text-slate-500">Select more than one role for dual access.</p><div class="mt-3 grid gap-3 sm:grid-cols-4">@foreach(['admin' => 'Admin', 'program_head' => 'Program Head', 'faculty' => 'Faculty', 'adviser' => 'Adviser'] as $value => $label)<label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3"><input type="checkbox" name="roles[]" value="{{ $value }}" class="size-4" @checked(in_array($value, old('roles', ['admin']), true))><span>{{ $label }}</span></label>@endforeach</div>@error('roles')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror @error('roles.*')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror</fieldset>
            <div class="sm:col-span-2"><label for="adviser_year_level">Advisory year level</label><select id="adviser_year_level" name="adviser_year_level"><option value="">Select when assigning Adviser</option>@foreach(range(1, 5) as $year)<option value="{{ $year }}" @selected(old('adviser_year_level') == $year)>Year {{ $year }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">Required for Adviser access. The adviser will see students from this year level only.</p>@error('adviser_year_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        </div>
        <div class="rounded-xl bg-blue-50 p-4 text-sm text-blue-800"><b>Access:</b> Full administrative access &nbsp; <b>Login:</b> Active</div>
        <div class="flex justify-end gap-3 border-t pt-5">
            <a href="{{ route('admin.accounts.index') }}" class="rounded-xl border px-5 py-3 font-semibold text-slate-700">Cancel</a>
            <button class="rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white">Create Account</button>
        </div>
    </form>
</div>
@endsection
