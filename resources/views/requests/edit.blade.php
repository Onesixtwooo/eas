@extends('layouts.app')
@section('title','Edit Excuse Request')
@section('content')
<div class="max-w-5xl">
    <a href="{{ route('requests.show', $item) }}" class="text-sm font-semibold text-[#245B8E]">← Back to request</a>
    <h1 class="mt-3 text-3xl font-bold text-slate-900">Edit Excuse Request</h1>
    <p class="mt-2 text-slate-500">Update the request information and save your changes.</p>
    <form method="post" action="{{ route('requests.update', $item) }}" enctype="multipart/form-data" class="mt-7 space-y-6">
        @csrf @method('PATCH')
        @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><b>Please correct the highlighted fields.</b><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="border-b pb-4 text-lg font-bold text-[#123A63]">Absence Information</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div><label for="absence-date">Date of absence *</label><input id="absence-date" type="date" name="absence_date" value="{{ old('absence_date', $item->absence_date->toDateString()) }}" required></div>
                <div><label for="subject">Subject & facilitator *</label><select id="subject" name="subject_id" required>@foreach($assignments->groupBy(fn($assignment) => $assignment->subject->year_level) as $yearLevel => $yearAssignments)<optgroup label="{{ $yearLevel ? 'Year '.$yearLevel : 'Other subjects' }}">@foreach($yearAssignments as $assignment)<option value="{{ $assignment->subject_id }}" @selected((int)old('subject_id',$item->subject_id)===$assignment->subject_id)>{{ $assignment->subject->code }} — {{ $assignment->subject->name }} ({{ $assignment->faculty->user->name }})</option>@endforeach</optgroup>@endforeach</select></div>
                <div><label for="start-time">Start time *</label><input id="start-time" type="time" name="start_time" value="{{ old('start_time', $item->start_time ? substr($item->start_time,0,5) : '') }}" required></div>
                <div><label for="end-time">End time *</label><input id="end-time" type="time" name="end_time" value="{{ old('end_time', $item->end_time ? substr($item->end_time,0,5) : '') }}" required></div>
                <div class="md:col-span-2"><label for="reason">Reason category *</label><select id="reason" name="reason_category_id" required><option value="">Select a reason</option>@foreach($reasons as $reason)<option value="{{ $reason->id }}" @selected((int)old('reason_category_id',$item->reason_category_id)===$reason->id)>{{ $reason->name }}</option>@endforeach</select></div>
                <div class="md:col-span-2"><label for="explanation">Detailed explanation *</label><textarea id="explanation" name="explanation" rows="5" minlength="20" maxlength="3000" required>{{ old('explanation', $item->explanation) }}</textarea><p class="mt-1 text-xs text-slate-400">Minimum 20 characters.</p></div>
            </div>
        </section>
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="border-b pb-4 text-lg font-bold text-[#123A63]">Supporting Information</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2"><label for="document">Supporting document <span class="font-normal text-slate-400">(optional)</span></label><input id="document" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png">@if($item->documents->isNotEmpty())<p class="mt-2 text-sm text-slate-600">Current: {{ $item->documents->pluck('original_name')->join(', ') }}. Leave empty to keep it.</p>@else<p class="mt-2 text-sm text-slate-500">No supporting document currently attached.</p>@endif<p class="mt-1 text-xs text-slate-400">Uploading a file replaces the current attachment. Maximum 5 MB.</p></div>
                <div><label for="guardian-name">Parent/guardian name</label><input id="guardian-name" name="guardian_name" value="{{ old('guardian_name', $item->guardian_name) }}"></div>
                <div><label for="guardian-contact">Contact number</label><input id="guardian-contact" name="guardian_contact" value="{{ old('guardian_contact', $item->guardian_contact) }}"></div>
            </div>
        </section>
        <div class="flex flex-wrap justify-end gap-3"><a href="{{ route('requests.show', $item) }}" class="rounded-xl border bg-white px-6 py-3 font-semibold">Cancel</a><button class="rounded-xl bg-[#123A63] px-6 py-3 font-semibold text-white hover:bg-[#0c2d4f]">Save Changes</button></div>
    </form>
</div>
@endsection
