@extends('layouts.app')

@section('title', 'Student Report')

@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Instructor</p><h1 class="mt-1 text-3xl font-bold text-slate-900">Student Report</h1><p class="mt-2 text-slate-500">Report absent students for {{ today()->format('F j, Y') }}.</p></div>
    @if($assignment)<span class="self-start rounded-full bg-blue-50 px-4 py-2 text-sm font-bold text-blue-700">Today’s report</span>@endif
</div>

@if($assignments->isEmpty())
    <div class="mt-7 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-800"><b>No active subject assignments</b><p class="mt-1 text-sm">Ask an administrator to assign your subjects before creating a student report.</p></div>
@else
    <section class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="px-5 pt-4"><p class="text-xs font-bold uppercase tracking-widest text-slate-500">Assigned Classes</p></div>
        <nav class="student-report-tabs mt-2 flex gap-2 overflow-x-auto px-4" aria-label="Assigned classes">
            @foreach($assignments as $item)
                <form method="post" action="{{ route('student-reports.select') }}" class="inline-block shrink-0">@csrf
                    <input type="hidden" name="class_key" value="{{ $item->class_key }}">
                    <button type="submit" aria-current="{{ $assignment?->id === $item->id ? 'page' : 'false' }}" class="whitespace-nowrap rounded-t-xl border-b-2 px-5 py-3 text-left text-sm font-semibold transition {{ $assignment?->id === $item->id ? 'border-[#123A63] bg-blue-50 text-[#123A63]' : 'border-transparent text-slate-500 hover:bg-slate-50 hover:text-[#123A63]' }}"><span class="block font-bold">{{ $item->subject->code }}</span><span class="mt-0.5 block text-xs font-normal">{{ $item->course->code }} · Year {{ $item->year_level }} · {{ $item->section ? 'Block '.$item->section->name : 'All Blocks' }}</span></button>
                </form>
            @endforeach
        </nav>
    </section>

    <form method="post" action="{{ route('student-reports.store') }}" class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">@csrf
        <input type="hidden" name="class_key" value="{{ $assignment->class_key }}">
        <div class="border-b border-slate-100 p-5"><h2 class="font-bold text-slate-900">Mark students absent today</h2><p class="mt-1 text-sm text-slate-500">Checked students will appear in their adviser’s daily absence report. Uncheck a student to remove today’s report.</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($students as $student)
                <div class="student-report-row hover:bg-slate-50">
                    <div class="student-report-identity">
                        <input id="absent-{{ $student->id }}" type="checkbox" name="absent_student_ids[]" value="{{ $student->id }}" class="student-report-checkbox size-5 shrink-0 rounded" @checked(in_array($student->id, old('absent_student_ids', $reportedStudentIds)))>
                        <label for="absent-{{ $student->id }}" class="student-report-details mb-0 cursor-pointer"><b class="block text-slate-900">{{ $student->user->name }}</b><span class="block text-sm text-slate-500">{{ $student->student_number }} · {{ $student->course->code }} Year {{ $student->year_level }}, Block {{ $student->section->name }}</span></label>
                        <label for="absent-{{ $student->id }}" class="student-report-badge mb-0 cursor-pointer rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-600">Absent</label>
                    </div>
                    <div class="student-report-comment">
                        <label for="comment-{{ $student->id }}" class="sr-only">Comment for {{ $student->user->name }}</label>
                        <input id="comment-{{ $student->id }}" name="comments[{{ $student->id }}]" value="{{ old('comments.'.$student->id, $reportedComments->get($student->id)) }}" maxlength="1000" placeholder="Optional comment…" class="text-sm">
                        @if($adviserActions->get($student->id))
                            @php($actionLabel = match($adviserActions->get($student->id)) {'acknowledged' => 'Acknowledged', 'resolved' => 'Resolved', default => 'Flagged Incorrect'})
                            <div class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800"><b>Adviser action:</b> {{ $actionLabel }}</div>
                        @endif
                        @if($adviserComments->get($student->id))<div class="mt-2 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800"><b>Adviser response:</b> {{ $adviserComments->get($student->id) }}</div>@endif
                    </div>
                </div>
            @empty
                <p class="px-6 py-12 text-center text-slate-500">No students are assigned to this class.</p>
            @endforelse
        </div>
        <div class="flex justify-end border-t bg-slate-50 p-4"><button class="student-report-save rounded-xl bg-[#123A63] px-6 py-3 font-semibold text-white hover:bg-[#245B8E]">Save Today’s Report</button></div>
    </form>
@endif

<style>
    .student-report-tabs { scrollbar-width: none; scroll-snap-type: x proximity; }
    .student-report-tabs::-webkit-scrollbar { display: none; }
    .student-report-tabs > form, .student-report-tabs > a { flex: 0 0 auto; scroll-snap-align: start; }
    .student-report-row { display: grid; gap: .85rem; padding: 1rem 1.25rem; }
    .student-report-identity { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: start; gap: .75rem; min-width: 0; }
    .student-report-checkbox { margin-top: .2rem; }
    .student-report-details { min-width: 0; overflow-wrap: anywhere; }
    .student-report-badge { white-space: nowrap; }
    .student-report-comment { min-width: 0; }
    @media (min-width: 1024px) {
        .student-report-row { grid-template-columns: minmax(0, 1fr) minmax(18rem, .75fr); align-items: center; }
    }
    @media (max-width: 479px) {
        .student-report-row { padding: 1rem; }
        .student-report-identity { grid-template-columns: auto minmax(0, 1fr); }
        .student-report-badge { grid-column: 2; justify-self: start; }
        .student-report-save { width: 100%; }
    }
</style>
@endsection
