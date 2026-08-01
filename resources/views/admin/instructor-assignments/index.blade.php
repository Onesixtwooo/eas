@extends('layouts.app')
@section('title', 'Instructor Assignments')
@section('content')
@php
    $editingAssignment = $editingAssignments->first();
@endphp
<div x-data="{ instructorModal: @js($errors->has('name') || $errors->has('designation')), assignmentModal: @js($editingAssignment || $errors->has('faculty_id') || $errors->has('course_id') || $errors->has('year_levels') || $errors->has('subject_ids')) }">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Administration</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">Instructor Assignments</h1>
            <p class="mt-2 text-slate-500">Assign an instructor to a year level, then choose the subjects they handle.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="button" @click="assignmentModal = true" class="rounded-xl border border-[#123A63] bg-white px-5 py-3 text-center font-semibold text-[#123A63] hover:bg-blue-50">+ New Assignment</button>
            <button type="button" @click="instructorModal = true" class="rounded-xl bg-[#123A63] px-5 py-3 text-center font-semibold text-white hover:bg-[#245B8E]">+ Add Instructor</button>
        </div>
    </div>

    <div x-show="instructorModal" x-cloak @keydown.escape.window="instructorModal = false" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="add-instructor-title">
        <div @click.outside="instructorModal = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b pb-4">
                <div>
                    <h2 id="add-instructor-title" class="text-xl font-bold text-[#123A63]">Add Instructor</h2>
                    <p class="mt-1 text-sm text-slate-500">Add an instructor who can be assigned to subjects. No portal account will be created.</p>
                </div>
                <button type="button" @click="instructorModal = false" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close">&times;</button>
            </div>
            <form method="post" action="{{ route('admin.instructors.store') }}" class="mt-5">
                @csrf
                @if($errors->has('name') || $errors->has('designation'))
                    <div class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('name') ?: $errors->first('designation') }}</div>
                @endif
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label for="modal-instructor-name">Instructor name *</label><input id="modal-instructor-name" name="name" value="{{ old('name') }}" required placeholder="e.g. Maria Santos Reyes"></div>
                    <div><label for="modal-designation">Designation *</label><input id="modal-designation" name="designation" value="{{ old('designation', 'Course Facilitator') }}" required></div>
                </div>
                <div class="mt-7 flex justify-end gap-3 border-t pt-5"><button type="button" @click="instructorModal = false" class="rounded-xl border px-5 py-3 font-semibold">Cancel</button><button class="rounded-xl bg-[#123A63] px-6 py-3 font-semibold text-white hover:bg-[#245B8E]">Add Instructor</button></div>
            </form>
        </div>
    </div>
    <div x-show="assignmentModal" x-cloak @keydown.escape.window="assignmentModal = false" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/50 p-4 sm:p-8" role="dialog" aria-modal="true" aria-labelledby="new-assignment-title">
        <div @click.outside="assignmentModal = false" class="mx-auto w-full max-w-6xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b pb-4">
                <div>
                    <h2 id="new-assignment-title" class="text-xl font-bold text-[#123A63]">{{ $editingAssignment ? 'Edit Assignment' : 'New Assignment' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Choose an instructor, course, year level, and the subjects they will handle.</p>
                </div>
                <button type="button" @click="assignmentModal = false" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close">&times;</button>
            </div>
<form method="post" action="{{ $editingAssignment ? route('admin.instructor-assignments.group-update', [$editingAssignment->faculty_id, $editingAssignment->course_id]) : route('admin.instructor-assignments.store') }}" class="mt-5" x-data="{ selectedCourse: '{{ old('course_id', $editingAssignment?->course_id) }}', selectedYears: @js(old('year_levels', old('year_level') ? [old('year_level')] : $editingAssignments->pluck('year_level')->unique()->values()->all())), isYearSelected(year) { return this.selectedYears.includes(String(year)) || this.selectedYears.includes(Number(year)); }, resetSubjects() { this.$refs.subjects.querySelectorAll('input[type=checkbox]').forEach(input => { if (input.dataset.course !== this.selectedCourse || (input.dataset.year && ! this.isYearSelected(input.dataset.year))) input.checked = false }) } }">
    @csrf
    @if($editingAssignment) @method('PUT') @endif
    @if($errors->any())
        <div class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mt-5 flex items-center gap-3">
        <span class="grid size-8 place-items-center rounded-full bg-[#123A63] text-sm font-bold text-white">1</span>
        <div>
            <h3 class="font-semibold text-slate-900">Assign the instructor to a year level</h3>
            <p class="text-sm text-slate-500">Choose who will teach and which student year they will handle.</p>
        </div>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
            <label>Instructor</label>
            <select name="faculty_id" @disabled($editingAssignment) required>
                <option value="">Select instructor</option>
                @foreach($faculty as $member)
                    <option value="{{ $member->id }}" @selected(old('faculty_id', $editingAssignment?->faculty_id) == $member->id)>{{ $member->display_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Course</label>
            <select name="course_id" x-model="selectedCourse" @change="resetSubjects()" @disabled($editingAssignment) required>
                <option value="">Select course</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-5">
        <label>Year levels *</label>
        <p class="mb-3 text-sm text-slate-500">Select one or more year levels for this instructor.</p>
        <div class="flex flex-wrap gap-3">
            @foreach(range(1, 5) as $year)
                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 hover:border-[#245B8E] hover:bg-blue-50"><input type="checkbox" name="year_levels[]" value="{{ $year }}" x-model="selectedYears" @change="resetSubjects()" class="size-4 w-auto">Year {{ $year }}</label>
            @endforeach
        </div>
    </div>

    <div class="mt-7 border-t pt-6">
        <div class="flex items-center gap-3">
            <span class="grid size-8 place-items-center rounded-full bg-[#123A63] text-sm font-bold text-white">2</span>
            <div>
                <h3 class="font-semibold text-slate-900">Assign subjects</h3>
                <p class="text-sm text-slate-500">Select every subject this instructor handles for the selected year levels. A subject may be assigned to more than one instructor.</p>
            </div>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" x-show="selectedCourse && selectedYears.length" x-ref="subjects">
            @foreach($subjects as $subject)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 hover:border-[#245B8E] hover:bg-blue-50" x-show="selectedCourse == '{{ $subject->course_id }}' && (! '{{ $subject->year_level }}' || isYearSelected('{{ $subject->year_level }}'))">
                    <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}" data-course="{{ $subject->course_id }}" data-year="{{ $subject->year_level }}" class="mt-1 size-4 w-auto" @checked(in_array($subject->id, old('subject_ids', $editingAssignments->pluck('subject_id')->unique()->all())))>
                    <span>
                        <b class="text-[#123A63]">{{ $subject->code }}</b>
                        <span class="block text-sm text-slate-500">{{ $subject->name }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-500" x-show="! selectedCourse || ! selectedYears.length">Choose a course and at least one year level first to see its available subjects.</p>
    </div>

            <div class="mt-7 flex justify-end gap-3 border-t pt-5">
                <button type="button" @click="assignmentModal = false" class="rounded-xl border px-5 py-3 font-semibold">Cancel</button>
                <button class="rounded-xl bg-[#123A63] px-6 py-3 font-semibold text-white">Save Year-Level Assignment</button>
            </div>
        </form>
        </div>
    </div>
</div>

<form method="get" class="mt-6 grid gap-3 rounded-2xl border bg-white p-4 shadow-sm sm:grid-cols-[1fr_1fr_auto]">
    <select name="course_id">
        <option value="">All courses</option>
        @foreach($courses as $course)<option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->code }}</option>@endforeach
    </select>
    <select name="year_level">
        <option value="">All year levels</option>
        @foreach(range(1, 5) as $year)<option value="{{ $year }}" @selected(request('year_level') == $year)>Year {{ $year }}</option>@endforeach
    </select>
    <button class="rounded-xl bg-[#123A63] px-6 font-semibold text-white">Filter</button>
</form>

<div class="mt-5 overflow-hidden rounded-2xl border bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[850px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr><th class="px-5 py-4">Instructor</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Year Level</th><th class="px-5 py-4">Subject</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($assignments as $group)
                    @php
                        $assignment = $group->first();
                        $allActive = $group->every(fn ($item) => $item->is_active);
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-4 font-semibold">{{ $assignment->faculty->display_name }}</td>
                        <td class="px-5 py-4">{{ $assignment->course->code }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                @foreach($group->pluck('year_level')->unique()->sort() as $year)
                                    <span class="whitespace-nowrap rounded-lg bg-blue-50 px-2 py-1 text-xs font-semibold text-[#123A63]">Year {{ $year }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                @foreach($group->unique('subject_id')->sortBy('subject.code') as $item)
                                    <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        <b class="text-[#123A63]">{{ $item->subject->code }}</b>
                                        <span class="ml-1 text-xs text-slate-500">{{ $item->subject->name }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $allActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $allActive ? 'Active' : ($group->contains('is_active', true) ? 'Partially active' : 'Inactive') }}</span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <form method="post" action="{{ route('admin.instructor-assignments.group-toggle', [$assignment->faculty_id, $assignment->course_id]) }}">@csrf @method('PATCH')<button class="rounded-lg border px-3 py-2 font-semibold">{{ $allActive ? 'Disable All' : 'Enable All' }}</button></form>
                                <a href="{{ route('admin.instructor-assignments.index', ['edit_faculty' => $assignment->faculty_id, 'edit_course' => $assignment->course_id]) }}" class="rounded-lg border border-[#123A63] px-3 py-2 font-semibold text-[#123A63] hover:bg-blue-50">Edit</a>
                                <form method="post" action="{{ route('admin.instructor-assignments.group-destroy', [$assignment->faculty_id, $assignment->course_id]) }}" onsubmit="return confirm('Remove all years and subjects in this instructor assignment?')">@csrf @method('DELETE')<button class="rounded-lg bg-red-50 px-3 py-2 font-semibold text-red-700">Remove All</button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-14 text-center text-slate-400">No instructor assignments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-5">{{ $assignments->links() }}</div>
@endsection
