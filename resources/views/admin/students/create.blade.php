@extends('layouts.app')
@section('title', 'Add Student')
@section('content')
<div class="max-w-4xl">
    <a href="{{ route('admin.students.index') }}" class="text-sm font-semibold text-[#245B8E]">← Back to students</a>
    <h1 class="mt-4 text-3xl font-bold text-slate-900">Add Student</h1>
    <p class="mt-2 text-slate-500">Create a student profile and portal account.</p>
    @if($errors->any())<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><b>Please correct the following:</b><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ route('admin.students.store') }}" class="mt-7 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <h2 class="border-b pb-4 text-lg font-bold text-[#123A63]">Student Information</h2>
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div><label for="student_number">Student ID *</label><input id="student_number" name="student_number" value="{{ old('student_number') }}" required placeholder="e.g. 2026-0001"></div>
            <div><label for="year_level">Year level *</label><select id="year_level" name="year_level" required><option value="">Select year level</option>@foreach(range(1, 5) as $year)<option value="{{ $year }}" @selected(old('year_level') == $year)>Year {{ $year }}</option>@endforeach</select></div>
            <div><label for="block">Block *</label><select id="block" name="block" required><option value="">Select block</option>@foreach(range('A', 'G') as $block)<option value="{{ $block }}" @selected(old('block') === $block)>Block {{ $block }}</option>@endforeach</select></div>
            <div><label for="first_name">First name *</label><input id="first_name" name="first_name" value="{{ old('first_name') }}" required></div>
            <div><label for="middle_name">Middle name <span class="font-normal text-slate-400">(optional)</span></label><input id="middle_name" name="middle_name" value="{{ old('middle_name') }}"></div>
            <div><label for="last_name">Last name *</label><input id="last_name" name="last_name" value="{{ old('last_name') }}" required></div>
            <div><label for="email">Email address *</label><input id="email" type="email" name="email" value="{{ old('email') }}" required></div>
            <div class="sm:col-span-2"><label for="address">Home address *</label><textarea id="address" name="address" rows="3" required>{{ old('address') }}</textarea></div>
            <div><label for="password">Temporary password *</label><input id="password" type="password" name="password" required placeholder="At least 8 characters"></div>
            <div><label for="password_confirmation">Confirm password *</label><input id="password_confirmation" type="password" name="password_confirmation" required></div>
        </div>
        <div class="mt-7 flex justify-end gap-3 border-t pt-5"><a href="{{ route('admin.students.index') }}" class="rounded-xl border px-5 py-3 font-semibold">Cancel</a><button class="rounded-xl bg-[#123A63] px-6 py-3 font-semibold text-white hover:bg-[#245B8E]">Create Student</button></div>
    </form>
</div>
@endsection
