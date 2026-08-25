@extends('layouts.app')
@section('title', 'Subjects')
@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Administration</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Subjects</h1>
        <p class="mt-2 text-slate-500">Manage the subjects available for each course and year level.</p>
    </div>
    <a href="{{ route('admin.subjects.create', ['semester' => request('semester', 1)]) }}" class="rounded-xl bg-[#123A63] px-5 py-3 text-center font-semibold text-white hover:bg-[#245B8E]">+ Add Subject</a>
</div>

<nav class="mt-7 grid grid-cols-2 gap-1 border-b border-slate-300 sm:flex sm:gap-2" aria-label="Subject semesters">
    @foreach([1 => 'First Semester', 2 => 'Second Semester'] as $semesterNumber => $semesterLabel)
        <a href="{{ route('admin.subjects.index', array_merge(request()->except(['page', 'semester']), ['semester' => $semesterNumber])) }}" class="-mb-px rounded-t-xl border px-3 py-3 text-center text-sm font-semibold sm:px-5 sm:text-base {{ $semester === $semesterNumber ? 'border-slate-300 border-b-white bg-white text-[#123A63]' : 'border-transparent text-slate-500 hover:text-[#123A63]' }}">{{ $semesterLabel }}</a>
    @endforeach
</nav>

<form method="get" class="mt-7 grid gap-3 rounded-2xl border bg-white p-4 shadow-sm sm:grid-cols-[1fr_220px_auto]">
    <input type="hidden" name="semester" value="{{ $semester }}">
    <div>
        <label class="sr-only" for="search">Search subjects</label>
        <input id="search" name="search" value="{{ request('search') }}" placeholder="Search by code, subject, or course">
    </div>
    <div>
        <label class="sr-only" for="year_level">Year level</label>
        <select id="year_level" name="year_level">
            <option value="">All year levels</option>
            @foreach(range(1, 5) as $year)<option value="{{ $year }}" @selected(request('year_level') == $year)>Year {{ $year }}</option>@endforeach
        </select>
    </div>
    <button class="rounded-xl bg-[#123A63] px-6 py-3 font-semibold text-white hover:bg-[#245B8E]">Search</button>
</form>

<div class="mt-7 space-y-3 md:hidden">
    @forelse($subjects as $subject)
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-[#123A63]">{{ $subject->code }}</p>
                    <h2 class="mt-1 font-semibold leading-snug text-slate-900">{{ $subject->name }}</h2>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $subject->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $subject->is_active ? 'Active' : 'Inactive' }}</span>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-4 border-y border-slate-100 py-4 text-sm">
                <div class="col-span-2">
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Course</dt>
                    <dd class="mt-1 text-slate-700"><b>{{ $subject->course->code }}</b> <span class="text-slate-500">&mdash; {{ $subject->course->name }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Year level</dt>
                    <dd class="mt-1 font-medium text-slate-700">Year {{ $subject->year_level }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Semester</dt>
                    <dd class="mt-1 font-medium text-slate-700">{{ $subject->semester === 1 ? 'First' : 'Second' }}</dd>
                </div>
            </dl>

            <a href="{{ route('admin.subjects.edit', $subject) }}" class="mt-4 block w-full rounded-xl border border-[#123A63] px-4 py-2.5 text-center text-sm font-semibold text-[#123A63] hover:bg-blue-50">Edit Subject</a>
        </article>
    @empty
        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center text-slate-500 shadow-sm">No subjects have been added for this semester yet.</div>
    @endforelse
</div>

<div class="mt-7 hidden overflow-hidden rounded-2xl border bg-white shadow-sm md:block">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr><th class="px-5 py-4">Code</th><th class="px-5 py-4">Subject</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Year Level</th><th class="px-5 py-4">Semester</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($subjects as $subject)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-4 font-semibold text-[#123A63]">{{ $subject->code }}</td>
                        <td class="px-5 py-4 font-medium text-slate-900">{{ $subject->name }}</td>
                        <td class="px-5 py-4">{{ $subject->course->code }} <span class="text-slate-500">— {{ $subject->course->name }}</span></td>
                        <td class="px-5 py-4">Year {{ $subject->year_level }}</td>
                        <td class="px-5 py-4">{{ $subject->semester === 1 ? 'First' : 'Second' }}</td>
                        <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $subject->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $subject->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('admin.subjects.edit', $subject) }}" class="rounded-lg border border-[#123A63] px-3 py-2 font-semibold text-[#123A63] hover:bg-blue-50">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-14 text-center text-slate-400">No subjects have been added for this semester yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-5">{{ $subjects->links() }}</div>
@endsection
