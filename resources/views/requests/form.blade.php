@extends('layouts.app')
@section('title','Submit Excuse Slip')
@section('content')
<div class="max-w-5xl">
    <h1 class="text-3xl font-bold text-slate-900">Submit Excuse Slip</h1>
    <p class="mt-2 text-slate-500">Complete all required information accurately.</p>
    <form method="post" action="{{ route('requests.store') }}" enctype="multipart/form-data" class="mt-7 space-y-6">
        @csrf
        @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><b>Please correct the highlighted fields.</b><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="border-b pb-4 text-lg font-bold text-[#123A63]">Absence Information</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div><label>Date of absence *</label><input type="date" name="absence_date" value="{{ old('absence_date') }}" required></div>
                <div><label>Subject & facilitator *</label><select name="subject_id" required><option value="">Select subject</option>@foreach($assignments as $assignment)<option value="{{ $assignment->subject_id }}" @selected(old('subject_id')==$assignment->subject_id)>{{ $assignment->subject->code }} — {{ $assignment->subject->name }} ({{ $assignment->faculty->user->name }})</option>@endforeach</select>@if($assignments->isEmpty())<p class="mt-2 text-sm text-red-600">No instructors are assigned to your course and year level. Please contact the administrator.</p>@endif</div>
                <div><label>Start time <span class="font-normal text-slate-400">(optional)</span></label><input type="time" name="start_time" value="{{ old('start_time') }}"></div>
                <div><label>End time <span class="font-normal text-slate-400">(optional)</span></label><input type="time" name="end_time" value="{{ old('end_time') }}"></div>
                <div class="md:col-span-2"><label>Reason category *</label><select name="reason_category_id" required><option value="">Select a reason</option>@foreach($reasons as $r)<option value="{{ $r->id }}" @selected(old('reason_category_id')==$r->id)>{{ $r->name }}</option>@endforeach</select></div>
                <div class="md:col-span-2"><label>Detailed explanation *</label><textarea name="explanation" rows="5" minlength="20" required placeholder="Provide clear details about your absence…">{{ old('explanation') }}</textarea><p class="mt-1 text-xs text-slate-400">Minimum 20 characters.</p></div>
            </div>
        </section>
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="border-b pb-4 text-lg font-bold text-[#123A63]">Supporting Information</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2"><label>Supporting document</label><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png"><p class="mt-1 text-xs text-slate-400">PDF, JPG, JPEG, or PNG. Maximum 5 MB.</p></div>
                <div><label>Parent/guardian name</label><input name="guardian_name" value="{{ old('guardian_name') }}"></div>
                <div><label>Contact number</label><input name="guardian_contact" value="{{ old('guardian_contact') }}"></div>
            </div>
        </section>
        <label class="flex items-start gap-3 rounded-2xl border bg-white p-5 font-normal"><input type="checkbox" name="declaration" value="1" class="mt-1 size-4 w-auto"><span>I certify that the information provided in this request is true and correct.</span></label>
        <div class="flex flex-wrap justify-end gap-3"><a href="{{ route('requests.index') }}" class="rounded-xl border bg-white px-6 py-3 font-semibold">Cancel</a><button name="intent" value="draft" class="rounded-xl border border-[#123A63] px-6 py-3 font-semibold text-[#123A63]">Save as Draft</button><button name="intent" value="submit" class="rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-700">Submit Request</button></div>
    </form>
</div>
@endsection
