@extends('layouts.app')

@section('title', 'My Advisory')

@section('content')
<div>
    <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Adviser</p>
    <h1 class="mt-1 text-3xl font-bold text-slate-900">My Advisory</h1>
    <p class="mt-2 text-slate-500">Students assigned to your Year {{ $yearLevel }} advisory.</p>
</div>

<section class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-4 pt-4">
        <p class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-500">Blocks</p>
        <nav class="advisory-block-tabs flex gap-2 overflow-x-auto" aria-label="Advisory blocks">
            <a href="{{ route('advisory.index', array_filter(['search' => request('search')])) }}" class="whitespace-nowrap rounded-t-xl border-b-2 px-5 py-3 text-sm font-semibold transition {{ request('section_id') ? 'border-transparent text-slate-500 hover:text-[#123A63]' : 'border-[#123A63] bg-blue-50 text-[#123A63]' }}">All Blocks</a>
            @foreach($sections as $section)
                <a href="{{ route('advisory.index', array_filter(['section_id' => $section->id, 'search' => request('search')])) }}" class="whitespace-nowrap rounded-t-xl border-b-2 px-5 py-3 text-sm font-semibold transition {{ (int) request('section_id') === $section->id ? 'border-[#123A63] bg-blue-50 text-[#123A63]' : 'border-transparent text-slate-500 hover:text-[#123A63]' }}">Block {{ $section->name }}<span class="ml-1 text-xs font-normal">({{ $section->course->code }})</span></a>
            @endforeach
        </nav>
    </div>
    <form method="get" class="grid gap-3 p-4 sm:grid-cols-[1fr_auto]">
        @if(request('section_id'))<input type="hidden" name="section_id" value="{{ request('section_id') }}">@endif
        <input name="search" value="{{ request('search') }}" placeholder="Search student ID, name, or email…">
        <div class="flex gap-2">
            <button class="rounded-xl bg-[#123A63] px-6 py-3 font-semibold text-white">Search</button>
            @if(request('search'))
                <a href="{{ route('advisory.index', array_filter(['section_id' => request('section_id')])) }}" class="grid place-items-center rounded-xl border px-4 text-sm font-semibold">Clear</a>
            @endif
        </div>
    </form>
</section>

<div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="advisory-table-wrap overflow-x-auto">
        <table class="advisory-table w-full min-w-[700px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="px-5 py-4">Student</th><th class="px-5 py-4">Student ID</th><th class="px-5 py-4">Course & Section</th><th class="px-5 py-4">Account Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($students as $student)
                    <tr class="hover:bg-slate-50">
                        <td class="advisory-col-student px-5 py-4"><p class="font-semibold text-slate-900">{{ $student->user->name }}</p><p class="text-xs text-slate-500">{{ $student->user->email }}</p></td>
                        <td class="advisory-col-id px-5 py-4 font-medium text-slate-700">{{ $student->student_number }}</td>
                        <td class="advisory-col-course px-5 py-4"><b>{{ $student->course->code }}</b><p class="text-xs text-slate-500">Year {{ $student->year_level }}, Section {{ $student->section->name }}</p></td>
                        <td class="advisory-col-status px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $student->user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">{{ $student->user->is_active ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-16 text-center text-slate-500">No students were found in this advisory.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $students->links() }}</div>

<style>
    .advisory-block-tabs { scrollbar-width: none; scroll-snap-type: x proximity; }
    .advisory-block-tabs::-webkit-scrollbar { display: none; }
    .advisory-block-tabs > a { flex: 0 0 auto; scroll-snap-align: start; }
    @media (max-width: 767px) {
        .advisory-table-wrap { overflow: visible; background: #f8fafc; }
        .advisory-table { display: block; min-width: 0; }
        .advisory-table thead { display: none; }
        .advisory-table tbody { display: grid; gap: .75rem; padding: .75rem; }
        .advisory-table tbody tr { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); overflow: hidden; border: 1px solid #cbd5e1; border-radius: .85rem; background: #fff; }
        .advisory-table tbody td { display: block; min-width: 0; border: 0; padding: .8rem; overflow-wrap: anywhere; }
        .advisory-table tbody td::before { display: block; margin-bottom: .35rem; color: #64748b; font-size: .65rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .advisory-col-student { grid-column: 1 / -1; }
        .advisory-col-student::before { content: "Student"; }
        .advisory-col-id::before { content: "Student ID"; }
        .advisory-col-course::before { content: "Course & section"; }
        .advisory-col-status { grid-column: 1 / -1; }
        .advisory-col-status::before { content: "Account status"; }
        .advisory-col-id, .advisory-col-course, .advisory-col-status { border-top: 1px solid #e2e8f0 !important; }
        .advisory-table td[colspan] { grid-column: 1 / -1; }
    }
    @media (max-width: 399px) {
        .advisory-table tbody tr { grid-template-columns: minmax(0, 1fr); }
        .advisory-col-student, .advisory-col-status { grid-column: 1; }
    }
</style>
@endsection
