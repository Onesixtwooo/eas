@extends('layouts.app')

@section('title', 'Edit User Account')

@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('admin.accounts.index') }}" class="text-sm font-semibold text-[#245B8E]">← Back to user accounts</a>
    <div class="mt-4">
        <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Account</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Edit User Account</h1>
        <p class="mt-2 text-slate-500">Update sign-in details, assigned roles, status, or password.</p>
    </div>

    <form method="post" action="{{ route('admin.accounts.update', $account) }}" class="mt-7 space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <div><label for="name">Full name</label><input id="name" name="name" value="{{ old('name', $account->name) }}" required>@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $account->email) }}" required>@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <fieldset class="sm:col-span-2"><legend class="font-semibold">Roles</legend><p class="mt-1 text-xs text-slate-500">Select more than one role to create a dual-role account.</p><div class="mt-3 grid gap-3 sm:grid-cols-5">@foreach(['admin' => 'Admin', 'program_head' => 'Program Head', 'faculty' => 'Faculty', 'adviser' => 'Adviser', 'student' => 'Student'] as $value => $label)<label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3"><input type="checkbox" name="roles[]" value="{{ $value }}" class="size-4" @checked(in_array($value, old('roles', $account->assignedRoles()), true))><span>{{ $label }}</span></label>@endforeach</div>@error('roles')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror @error('roles.*')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror</fieldset>
            <div class="sm:col-span-2"><label for="adviser_year_level">Advisory year level</label><select id="adviser_year_level" name="adviser_year_level"><option value="">Select when assigning Adviser</option>@foreach(range(1, 5) as $year)<option value="{{ $year }}" @selected(old('adviser_year_level', $account->adviser_year_level) == $year)>Year {{ $year }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">Required for Adviser access. The adviser will see students from this year level only.</p>@error('adviser_year_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            @if(!$account->faculty && $unlinkedFaculty->isNotEmpty())
                <div class="sm:col-span-2"><label for="faculty_id">Instructor profile for Faculty access</label><select id="faculty_id" name="faculty_id"><option value="">Create a new faculty profile</option>@foreach($unlinkedFaculty as $member)<option value="{{ $member->id }}" @selected((string) old('faculty_id') === (string) $member->id)>{{ $member->display_name }} — {{ $member->designation }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">When adding the Faculty role, connect the account to its existing instructor assignment profile so assigned student requests appear.</p>@error('faculty_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            @endif
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
