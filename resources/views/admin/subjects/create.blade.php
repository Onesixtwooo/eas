@extends('layouts.app')
@section('title', $subject->exists ? 'Edit Subject' : 'Add Subject')
@section('content')
<div class="max-w-4xl">
    <a href="{{ route('admin.subjects.index') }}" class="text-sm font-semibold text-[#245B8E]">&larr; Back to subjects</a>
    <h1 class="mt-4 text-3xl font-bold text-slate-900">{{ $subject->exists ? 'Edit Subject' : 'Add Subject' }}</h1>
    <p class="mt-2 text-slate-500">{{ $subject->exists ? 'Update this subject’s course, year level, or details.' : 'Add a subject to a course and specify the student year level that takes it.' }}</p>

    @if($errors->any())
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><b>Please correct the following:</b><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $subject->exists ? route('admin.subjects.update', $subject) : route('admin.subjects.store') }}" class="mt-7 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @if($subject->exists) @method('PUT') @endif
        <h2 class="border-b pb-4 text-lg font-bold text-[#123A63]">Subject Information</h2>
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div><label for="code">Subject code *</label><input id="code" name="code" value="{{ old('code', $subject->code) }}" required placeholder="e.g. PF101"></div>
            <div><label for="name">Subject name *</label><input id="name" name="name" value="{{ old('name', $subject->name) }}" required placeholder="e.g. Programming Fundamentals"></div>
            <div>
                <label for="course_id">Course *</label>
                <select id="course_id" name="course_id" required>
                    <option value="">Select course</option>
                    @foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id', $subject->course_id) == $course->id)>{{ $course->code }} &mdash; {{ $course->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="year_level">Year level *</label>
                <select id="year_level" name="year_level" required>
                    <option value="">Select year level</option>
                    @foreach(range(1, 5) as $year)<option value="{{ $year }}" @selected(old('year_level', $subject->year_level) == $year)>Year {{ $year }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="semester">Semester *</label>
                <select id="semester" name="semester" required>
                    <option value="1" @selected((int)old('semester', $subject->semester ?? request('semester', 1)) === 1)>First Semester</option>
                    <option value="2" @selected((int)old('semester', $subject->semester ?? request('semester', 1)) === 2)>Second Semester</option>
                </select>
            </div>
        </div>
        <div class="mt-7 flex justify-end gap-3 border-t pt-5"><a href="{{ route('admin.subjects.index') }}" class="rounded-xl border px-5 py-3 font-semibold">Cancel</a><button class="rounded-xl bg-[#123A63] px-6 py-3 font-semibold text-white hover:bg-[#245B8E]">{{ $subject->exists ? 'Save Changes' : 'Add Subject' }}</button></div>
    </form>
</div>
@endsection
