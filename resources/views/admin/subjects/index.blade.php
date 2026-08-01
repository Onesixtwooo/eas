@extends('layouts.app')
@section('title', 'Subjects')
@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Administration</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Subjects</h1>
        <p class="mt-2 text-slate-500">Manage the subjects available for each course and year level.</p>
    </div>
    <a href="{{ route('admin.subjects.create') }}" class="rounded-xl bg-[#123A63] px-5 py-3 text-center font-semibold text-white hover:bg-[#245B8E]">+ Add Subject</a>
</div>

<form method="get" class="mt-7 grid gap-3 rounded-2xl border bg-white p-4 shadow-sm sm:grid-cols-[1fr_220px_auto]">
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

<div class="mt-7 overflow-hidden rounded-2xl border bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr><th class="px-5 py-4">Code</th><th class="px-5 py-4">Subject</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Year Level</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($subjects as $subject)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-4 font-semibold text-[#123A63]">{{ $subject->code }}</td>
                        <td class="px-5 py-4 font-medium text-slate-900">{{ $subject->name }}</td>
                        <td class="px-5 py-4">{{ $subject->course->code }} <span class="text-slate-500">— {{ $subject->course->name }}</span></td>
                        <td class="px-5 py-4">Year {{ $subject->year_level }}</td>
                        <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $subject->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $subject->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('admin.subjects.edit', $subject) }}" class="rounded-lg border border-[#123A63] px-3 py-2 font-semibold text-[#123A63] hover:bg-blue-50">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-14 text-center text-slate-400">No subjects have been added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-5">{{ $subjects->links() }}</div>
@endsection
