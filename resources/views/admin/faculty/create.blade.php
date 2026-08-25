@extends('layouts.app')
@section('title', 'Add Faculty Account')
@section('content')
<div class="mx-auto max-w-3xl" x-data="{ existing: @js((string) old('faculty_id', request('faculty_id'))) }">
    <a href="{{ route('admin.faculty.index') }}" class="text-sm font-semibold text-[#245B8E]">&larr; Back to faculty</a>
    <h1 class="mt-4 text-3xl font-bold text-slate-900">Add Faculty Account</h1>
    <p class="mt-2 text-slate-500">Create a login for a new faculty member or connect one already used in instructor assignments.</p>

    <form method="post" action="{{ route('admin.faculty.store') }}" class="mt-7 space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><b>Please correct the highlighted information.</b></div>@endif
        <div>
            <label for="faculty_id">Existing instructor (optional)</label>
            <select id="faculty_id" name="faculty_id" x-model="existing">
                <option value="">Create a new faculty profile</option>
                @foreach($unlinkedFaculty as $member)<option value="{{ $member->id }}">{{ $member->display_name }} — {{ $member->designation }}</option>@endforeach
            </select>
            @error('faculty_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-5 sm:grid-cols-2" x-show="!existing">
            <div><label for="name">Full name *</label><input id="name" name="name" value="{{ old('name') }}" :required="!existing">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="employee_number">Employee number</label><input id="employee_number" name="employee_number" value="{{ old('employee_number') }}">@error('employee_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2"><label for="designation">Designation *</label><input id="designation" name="designation" value="{{ old('designation', 'Course Facilitator') }}" :required="!existing">@error('designation')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        </div>
        <div><label for="email">Faculty email address *</label><input id="email" type="email" name="email" value="{{ old('email') }}" required>@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div class="rounded-xl bg-blue-50 p-4 text-sm text-blue-800"><b>Already an admin?</b> Enter the same admin email. The selected instructor will be connected to that account and Faculty access will be added—no duplicate account or new password. For a new email, a temporary password will be sent.</div>
        <div class="flex justify-end gap-3 border-t pt-5"><a href="{{ route('admin.faculty.index') }}" class="rounded-xl border px-5 py-3 font-semibold">Cancel</a><button class="rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white">Connect or Create Account</button></div>
    </form>
</div>
@endsection
