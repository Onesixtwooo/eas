@extends('layouts.app')

@section('title', 'Daily Absences')

@section('content')
<div>
    <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Adviser</p>
    <h1 class="mt-1 text-3xl font-bold text-slate-900">Daily Absences</h1>
    <p class="mt-2 text-slate-500">Instructor reports for your Year {{ $yearLevel }} advisory on {{ today()->format('F j, Y') }}.</p>
</div>

<section class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-4 pt-4">
        <p class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-500">Blocks</p>
        <nav class="flex gap-2 overflow-x-auto" aria-label="Absence report blocks">
            <a href="{{ route('advisory.absences') }}" class="whitespace-nowrap rounded-t-xl border-b-2 px-5 py-3 text-sm font-semibold transition {{ request('section_id') ? 'border-transparent text-slate-500 hover:text-[#123A63]' : 'border-[#123A63] bg-blue-50 text-[#123A63]' }}">All Blocks</a>
            @foreach($sections as $section)
                <a href="{{ route('advisory.absences', ['section_id' => $section->id]) }}" class="whitespace-nowrap rounded-t-xl border-b-2 px-5 py-3 text-sm font-semibold transition {{ (int) request('section_id') === $section->id ? 'border-[#123A63] bg-blue-50 text-[#123A63]' : 'border-transparent text-slate-500 hover:text-[#123A63]' }}">Block {{ $section->name }}<span class="ml-1 text-xs font-normal">({{ $section->course->code }})</span></a>
            @endforeach
        </nav>
    </div>
</section>

<section class="mt-5 overflow-hidden rounded-2xl border border-red-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-4 border-b border-red-100 bg-red-50 p-5">
        <div><h2 class="font-bold text-red-900">Absent Today</h2><p class="mt-1 text-sm text-red-700">{{ request('section_id') ? 'Showing the selected block.' : 'Showing all advisory blocks.' }}</p></div>
        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-red-600 font-bold text-white">{{ $absentStudents->count() }}</span>
    </div>
    <div class="divide-y divide-slate-100">
        @forelse($absentStudents as $student)
            <article class="grid gap-4 p-5 lg:grid-cols-[minmax(15rem,.65fr)_minmax(0,1.35fr)]">
                <div><p class="font-semibold text-slate-900">{{ $student->user->name }}</p><p class="text-sm text-slate-500">{{ $student->student_number }} · {{ $student->course->code }} Year {{ $student->year_level }}, Block {{ $student->section->name }}</p></div>
                <div class="grid gap-3">
                    @foreach($student->dailyAbsenceReports as $report)
                        @php
                            $actionLabel = match($report->adviser_action) {
                                'acknowledged' => 'Acknowledged',
                                'resolved' => 'Resolved',
                                'flagged_incorrect' => 'Flagged Incorrect',
                                default => 'Pending Review',
                            };
                            $actionColor = match($report->adviser_action) {
                                'acknowledged' => 'bg-blue-100 text-blue-800',
                                'resolved' => 'bg-emerald-100 text-emerald-800',
                                'flagged_incorrect' => 'bg-amber-100 text-amber-800',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <div class="rounded-xl border border-red-100 bg-red-50 p-3 text-xs text-red-700">
                            <div class="flex flex-wrap items-center justify-between gap-2"><b>{{ $report->assignment->subject->code }} · {{ $report->assignment->faculty->display_name }}</b><span class="rounded-full px-3 py-1 font-bold {{ $actionColor }}">{{ $actionLabel }}</span></div>
                            @if($report->comment)<p class="mt-2 text-slate-600"><b>Instructor comment:</b> {{ $report->comment }}</p>@endif

                            <form method="post" action="{{ route('advisory.absences.action', $report) }}" class="mt-3 flex flex-wrap gap-2">@csrf @method('PATCH')
                                <button name="adviser_action" value="acknowledged" class="rounded-lg border border-blue-300 px-3 py-2 font-semibold text-blue-800 hover:bg-blue-100">Acknowledge</button>
                                <button name="adviser_action" value="resolved" class="rounded-lg border border-emerald-300 px-3 py-2 font-semibold text-emerald-800 hover:bg-emerald-100">Mark Resolved</button>
                                <button name="adviser_action" value="flagged_incorrect" class="rounded-lg border border-amber-300 px-3 py-2 font-semibold text-amber-800 hover:bg-amber-100">Flag Incorrect</button>
                            </form>

                            <form method="post" action="{{ route('advisory.absences.comment', $report) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">@csrf @method('PATCH')
                                <label for="adviser-comment-{{ $report->id }}" class="sr-only">Adviser comment for {{ $student->user->name }}</label>
                                <input id="adviser-comment-{{ $report->id }}" name="adviser_comment" value="{{ old('adviser_comment', $report->adviser_comment) }}" maxlength="1000" placeholder="Add a comment back…" class="bg-white text-sm">
                                <button class="rounded-lg bg-[#123A63] px-4 py-2 font-semibold text-white">{{ $report->adviser_comment ? 'Update Reply' : 'Send Reply' }}</button>
                            </form>
                            @error('adviser_comment')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </article>
        @empty
            <p class="px-6 py-14 text-center text-slate-500">No students have been reported absent today.</p>
        @endforelse
    </div>
</section>
@endsection
