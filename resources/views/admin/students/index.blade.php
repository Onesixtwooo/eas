@extends('layouts.app')

@section('title', 'Students')

@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Administration</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Students</h1>
        <p class="mt-2 text-slate-500">View and manage registered student accounts.</p>
    </div>
    <a href="{{ route('admin.students.create') }}" class="rounded-xl bg-[#123A63] px-5 py-3 text-center font-semibold text-white hover:bg-[#245B8E]">
        + Add Student
    </a>
</div>

<div class="mt-7 grid gap-4 sm:grid-cols-4">
    @foreach([
        ['All Students', $summary['total'], 'bg-blue-50 text-blue-700'],
        ['Pending Verification', $summary['pending'], 'bg-amber-50 text-amber-700'],
        ['Active Accounts', $summary['active'], 'bg-emerald-50 text-emerald-700'],
        ['Inactive Accounts', $summary['inactive'], 'bg-red-50 text-red-700'],
    ] as [$label, $count, $color])
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ $count }}</p></div>
                <span class="grid size-11 place-items-center rounded-xl {{ $color }}">♟</span>
            </div>
        </div>
    @endforeach
</div>

<form method="get" class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_240px_180px_auto]">
    <input name="search" value="{{ request('search') }}" placeholder="Search ID, name, or email…">
    <select name="section_id">
        <option value="">All sections</option>
        @foreach($sections as $section)
            <option value="{{ $section->id }}" @selected(request('section_id') == $section->id)>
                {{ $section->course->code }} — Year {{ $section->year_level }}, {{ $section->name }}
            </option>
        @endforeach
    </select>
    <select name="status">
        <option value="">All statuses</option>
        <option value="active" @selected(request('status') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        <option value="pending" @selected(request('status') === 'pending')>Pending verification</option>
    </select>
    <div class="flex gap-2">
        <button class="rounded-xl bg-[#123A63] px-6 font-semibold text-white">Filter</button>
        @if(request()->hasAny(['search', 'section_id', 'status']))
            <a href="{{ route('admin.students.index') }}" class="grid place-items-center rounded-xl border px-4 text-sm font-semibold">Clear</a>
        @endif
    </div>
</form>

<div class="mt-5" x-data="{ selected: [], allIds: @js($students->pluck('id')->values()), toggleAll(event) { this.selected = event.target.checked ? [...this.allIds] : [] } }">
    <form id="bulk-delete-form" method="post" action="{{ route('admin.students.bulk-destroy') }}" class="mb-3 flex min-h-11 items-center justify-between rounded-xl border border-red-200 bg-red-50 px-4 py-2" x-show="selected.length > 0" x-cloak onsubmit="return confirm('Permanently delete all selected students and their linked records? This cannot be undone.')">
        @csrf @method('DELETE')
        <p class="text-sm font-semibold text-red-800"><span x-text="selected.length"></span> student(s) selected</p>
        <button class="rounded-lg bg-[#B3262E] px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Delete Selected</button>
    </form>
    @error('student_ids')<div class="mb-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>@enderror
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[950px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="w-12 px-5 py-4"><input type="checkbox" class="size-4 w-auto rounded" aria-label="Select all students on this page" @change="toggleAll($event)" :checked="allIds.length > 0 && selected.length === allIds.length"></th>
                    <th class="px-5 py-4">Student</th>
                    <th class="px-5 py-4">Student ID</th>
                    <th class="px-5 py-4">Course & Section</th>
                    <th class="px-5 py-4">Address</th>
                    <th class="px-5 py-4">Status</th>
                    <th class="px-5 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($students as $student)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-4"><input form="bulk-delete-form" type="checkbox" name="student_ids[]" value="{{ $student->id }}" x-model.number="selected" class="size-4 w-auto rounded" aria-label="Select {{ $student->user->name }}"></td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-[#123A63] font-bold text-white">{{ strtoupper(substr($student->user->name, 0, 1)) }}</span>
                                <div><p class="font-semibold text-slate-900">{{ $student->user->name }}</p><p class="text-xs text-slate-500">{{ $student->user->email }}</p></div>
                            </div>
                        </td>
                        <td class="px-5 py-4 font-semibold text-[#123A63]">{{ $student->student_number }}</td>
                        <td class="px-5 py-4"><b>{{ $student->course->code }}</b><p class="text-xs text-slate-500">Year {{ $student->year_level }}, Section {{ $student->section->name }}</p></td>
                        <td class="max-w-xs truncate px-5 py-4 text-slate-600">{{ $student->address ?: 'Not provided' }}</td>
                        <td class="px-5 py-4">
                            @if(! $student->user->registration_verified_at)
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Pending verification</span>
                            @else
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $student->user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">{{ $student->user->is_active ? 'Active' : 'Inactive' }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.students.show', $student) }}" class="rounded-lg border px-3 py-2 font-semibold text-[#245B8E] hover:bg-blue-50">View</a>
                                @if(! $student->user->registration_verified_at)
                                    <form method="post" action="{{ route('admin.students.verify', $student) }}" onsubmit="return confirm('Verify this student registration and allow login?')">
                                        @csrf @method('PATCH')
                                        <button class="rounded-lg bg-emerald-600 px-3 py-2 font-semibold text-white hover:bg-emerald-700">Verify</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('admin.students.status', $student) }}" onsubmit="return confirm('{{ $student->user->is_active ? 'Deactivate' : 'Activate' }} this student account?')">
                                    @csrf @method('PATCH')
                                    <button class="rounded-lg px-3 py-2 font-semibold {{ $student->user->is_active ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">{{ $student->user->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                                <form method="post" action="{{ route('admin.students.destroy', $student) }}" onsubmit="return confirm('Permanently delete {{ addslashes($student->user->name) }} and all linked records? This cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button class="rounded-lg border border-red-300 px-3 py-2 font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-16 text-center"><p class="font-semibold text-slate-700">No students found</p><p class="mt-1 text-sm text-slate-400">Try changing your search or filters.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="mt-5">{{ $students->links() }}</div>
@endsection
