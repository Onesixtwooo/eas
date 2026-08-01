@extends('layouts.app')

@section('title', $student->user->name)

@section('content')
<a href="{{ route('admin.students.index') }}" class="text-sm font-semibold text-[#245B8E]">← Back to students</a>

<div class="mt-5 flex flex-col justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center">
    <div class="flex items-center gap-4">
        <span class="grid size-16 place-items-center rounded-2xl bg-[#123A63] text-2xl font-bold text-white">{{ strtoupper(substr($student->user->name, 0, 1)) }}</span>
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ $student->user->name }}</h1>
            <p class="mt-1 text-slate-500">{{ $student->student_number }}</p>
        </div>
    </div>
    <span class="w-fit rounded-full px-4 py-2 text-sm font-bold {{ $student->user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">{{ $student->user->is_active ? 'Active Account' : 'Inactive Account' }}</span>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-[#123A63]">Student Information</h2>
        <dl class="mt-6 grid gap-6 sm:grid-cols-2">
            @foreach([
                ['Full name', $student->user->name],
                ['Student ID', $student->student_number],
                ['Email address', $student->user->email],
                ['Course', $student->course->name],
                ['Year level', 'Year '.$student->year_level],
                ['Section', $student->section->name],
            ] as [$label, $value])
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mt-1 font-medium text-slate-800">{{ $value }}</dd></div>
            @endforeach
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Home address</dt><dd class="mt-1 font-medium text-slate-800">{{ $student->address ?: 'Not provided' }}</dd></div>
        </dl>
    </section>

    <aside class="space-y-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold text-slate-900">Assessment form</h2>
            <p class="mt-2 text-sm text-slate-500">Use this document to verify the student's enrollment information.</p>
            @if($student->assessment_form_path)
                <a href="{{ route('admin.students.assessment-form', $student) }}" target="_blank" class="mt-4 block overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                    <img src="{{ route('admin.students.assessment-form', $student) }}" alt="Assessment form uploaded by {{ $student->user->name }}" class="max-h-72 w-full object-contain">
                </a>
                <a href="{{ route('admin.students.assessment-form', $student) }}" target="_blank" class="mt-3 block text-center text-sm font-semibold text-[#245B8E]">View full image</a>
            @else
                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm font-medium text-amber-800">No assessment form uploaded.</p>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Total excuse requests</p><p class="mt-2 text-4xl font-bold text-[#123A63]">{{ $student->requests_count }}</p>
        </div>
        <form method="post" action="{{ route('admin.students.status', $student) }}" onsubmit="return confirm('{{ $student->user->is_active ? 'Deactivate' : 'Activate' }} this student account?')" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @method('PATCH')
            <h2 class="font-bold text-slate-900">Account access</h2>
            <p class="mt-2 text-sm text-slate-500">{{ $student->user->is_active ? 'Deactivation prevents this student from accessing protected pages.' : 'Activation restores access to the student portal.' }}</p>
            <button class="mt-5 w-full rounded-xl px-4 py-3 font-semibold text-white {{ $student->user->is_active ? 'bg-[#B3262E]' : 'bg-emerald-600' }}">{{ $student->user->is_active ? 'Deactivate Account' : 'Activate Account' }}</button>
        </form>
        <form method="post" action="{{ route('admin.students.destroy', $student) }}" onsubmit="return confirm('Permanently delete this student and all linked records? This cannot be undone.')" class="rounded-2xl border border-red-200 bg-red-50 p-6">
            @csrf @method('DELETE')
            <h2 class="font-bold text-red-900">Delete student</h2>
            <p class="mt-2 text-sm text-red-700">This permanently removes the student account, requests, histories, and uploaded documents.</p>
            <button class="mt-5 w-full rounded-xl bg-[#B3262E] px-4 py-3 font-semibold text-white hover:bg-red-800">Delete Student Permanently</button>
        </form>
    </aside>
</div>
@endsection
