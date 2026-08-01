@extends('layouts.app')
@section('title', 'Add Instructor')
@section('content')
<div class="max-w-4xl">
    <a href="{{ route('admin.instructor-assignments.index') }}" class="text-sm font-semibold text-[#245B8E]">← Back to instructor assignments</a>
    <h1 class="mt-4 text-3xl font-bold text-slate-900">Add Instructor to List</h1>
    <p class="mt-2 text-slate-500">Add an instructor who can be assigned to student year levels and subjects. No portal account will be created.</p>
    @if($errors->any())
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><b>Please correct the following:</b><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="post" action="{{ route('admin.instructors.store') }}" class="mt-7 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <h2 class="border-b pb-4 text-lg font-bold text-[#123A63]">Instructor Information</h2>
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div><label for="designation">Designation *</label><input id="designation" name="designation" value="{{ old('designation', 'Course Facilitator') }}" required></div>
            <div class="sm:col-span-2"><label for="name">Instructor name *</label><input id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Maria Santos Reyes"></div>
        </div>
        <div class="mt-7 flex justify-end gap-3 border-t pt-5"><a href="{{ route('admin.instructor-assignments.index') }}" class="rounded-xl border px-5 py-3 font-semibold">Cancel</a><button class="rounded-xl bg-[#123A63] px-6 py-3 font-semibold text-white hover:bg-[#245B8E]">Add Instructor</button></div>
    </form>
</div>
@endsection
