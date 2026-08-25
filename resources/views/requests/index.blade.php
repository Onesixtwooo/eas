@extends('layouts.app')
@section('title', auth()->user()->role === 'student' ? 'My Excuse Requests' : 'Excuse Requests')
@section('content')
<div class="flex items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-900">{{ auth()->user()->role === 'student' ? 'My Excuse Requests' : 'Excuse Requests' }}</h1>
        <p class="mt-2 text-slate-500">{{ auth()->user()->role === 'student' ? 'View and track excuse requests submitted from your account.' : (auth()->user()->role === 'faculty' ? 'View confirmed requests for your assigned subjects.' : 'Review requests grouped by student and status.') }}</p>
    </div>
    @if(auth()->user()->role === 'student')
        <a href="{{ route('requests.create') }}" class="rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white hover:bg-emerald-700">+ New request</a>
    @elseif(in_array(auth()->user()->role, ['admin', 'program_head']))
        <button type="button" onclick="document.getElementById('slip-settings').showModal()" class="rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold text-[#123A63] shadow-sm hover:bg-slate-50">Settings</button>
    @endif
</div>
@if(auth()->user()->role === 'faculty')
    <nav class="mt-7 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Assigned subjects">
        <div class="flex min-w-max gap-2">
            @php($allSubjectQuery = request()->except(['subject_id', 'page']))
            <a href="{{ route('requests.index', $allSubjectQuery) }}" class="rounded-xl px-4 py-3 text-sm font-semibold {{ !$selectedSubjectId ? 'bg-[#123A63] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">All Subjects</a>
            @foreach($assignedSubjects as $assignedSubject)
                @php($subjectQuery = array_merge(request()->except('page'), ['subject_id' => $assignedSubject->id]))
                <a href="{{ route('requests.index', $subjectQuery) }}" title="{{ $assignedSubject->name }}" class="rounded-xl px-4 py-3 text-sm font-semibold {{ $selectedSubjectId === $assignedSubject->id ? 'bg-[#123A63] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}"><span class="block">{{ $assignedSubject->code }}</span><span class="mt-0.5 block max-w-48 truncate text-xs font-normal opacity-75">{{ $assignedSubject->name }}</span></a>
            @endforeach
        </div>
    </nav>
@endif
@if(in_array(auth()->user()->role, ['admin', 'program_head'], true))
    <nav class="mt-7 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Student year levels">
        <div class="flex min-w-max gap-2">
            @php($allYearsQuery = request()->except(['year_level', 'page']))
            <a href="{{ route('requests.index', $allYearsQuery) }}" class="flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold {{ !$selectedYearLevel ? 'bg-[#123A63] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">All Years @if($pendingCountTotal)<span class="inline-flex min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[11px] font-bold leading-4 text-white" title="{{ $pendingCountTotal }} pending requests">{{ $pendingCountTotal }}</span>@endif</a>
            @foreach($yearLevels as $yearLevel)
                @php($yearQuery = array_merge(request()->except('page'), ['year_level' => $yearLevel]))
                @php($yearPending = (int) ($pendingCountsByYear[$yearLevel] ?? 0))
                <a href="{{ route('requests.index', $yearQuery) }}" class="flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold {{ $selectedYearLevel === (int) $yearLevel ? 'bg-[#123A63] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">Year {{ $yearLevel }} @if($yearPending)<span class="inline-flex min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[11px] font-bold leading-4 text-white" title="{{ $yearPending }} pending {{ Str::plural('request', $yearPending) }}">{{ $yearPending }}</span>@endif</a>
            @endforeach
        </div>
    </nav>
@endif
@if(in_array(auth()->user()->role, ['admin', 'program_head']))
<dialog id="slip-settings" class="m-auto w-[min(28rem,calc(100%-2rem))] rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
    <form method="post" action="{{ route('requests.settings.update') }}" class="p-6">
        @csrf @method('PUT')
        <div class="flex items-start justify-between gap-4">
            <div><h2 class="text-xl font-bold text-slate-900">Excuse Slip Settings</h2><p class="mt-1 text-sm text-slate-500">Set the program head name printed under “Issued and Verified by.”</p></div>
            <button type="button" onclick="document.getElementById('slip-settings').close()" class="text-2xl leading-none text-slate-400" aria-label="Close">&times;</button>
        </div>
        <div class="mt-6"><label for="program_head_name">Program head name</label><input id="program_head_name" name="program_head_name" value="{{ old('program_head_name', $programHeadName) }}" maxlength="255" required>@error('program_head_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div class="mt-6 flex justify-end gap-3 border-t pt-5"><button type="button" onclick="document.getElementById('slip-settings').close()" class="rounded-xl border px-5 py-3 font-semibold">Cancel</button><button class="rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white">Save Settings</button></div>
    </form>
</dialog>
@if($errors->has('program_head_name'))<script>document.getElementById('slip-settings').showModal()</script>@endif
@endif
<form class="mt-7 grid gap-3 rounded-2xl border bg-white p-4 shadow-sm sm:grid-cols-[1fr_220px_auto]">
    @if(auth()->user()->role === 'faculty' && $selectedSubjectId)<input type="hidden" name="subject_id" value="{{ $selectedSubjectId }}">@endif
    @if(in_array(auth()->user()->role, ['admin', 'program_head'], true) && $selectedYearLevel)<input type="hidden" name="year_level" value="{{ $selectedYearLevel }}">@endif
    <input name="search" value="{{ request('search') }}" placeholder="{{ auth()->user()->role === 'student' ? 'Search reference or subject…' : 'Search reference, student, or subject…' }}">
    <select name="status">
        @if(auth()->user()->role === 'student')
            <option value="">All statuses</option>
            @foreach(['draft','submitted','under_review','returned','approved','rejected','cancelled','acknowledged','completed'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        @elseif(auth()->user()->role === 'faculty')
            <option value="confirmed" @selected(request('status', 'confirmed') === 'confirmed')>All Confirmed</option>
            <option value="approved" @selected(request('status') === 'approved')>Approved</option>
            <option value="acknowledged" @selected(request('status') === 'acknowledged')>Acknowledged</option>
            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
        @else
            <option value="pending" @selected(request('status', 'pending') === 'pending')>Pending</option>
            <option value="approved" @selected(request('status') === 'approved')>Approved</option>
            <option value="under_review" @selected(request('status') === 'under_review')>Under Review</option>
            <option value="submitted" @selected(request('status') === 'submitted')>Submitted</option>
            <option value="returned" @selected(request('status') === 'returned')>Returned</option>
            <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
            <option value="acknowledged" @selected(request('status') === 'acknowledged')>Acknowledged</option>
            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
            <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            <option value="all" @selected(request('status') === 'all')>All statuses</option>
        @endif
    </select>
    <button class="rounded-xl bg-[#123A63] px-6 font-semibold text-white">Filter</button>
</form>
<div class="mt-5 overflow-hidden rounded-2xl border bg-white shadow-sm">
    @if(auth()->user()->role === 'student')
        @include('requests._table', ['items' => $requests])
    @else
        @include('requests._grouped', ['groups' => $studentGroups])
    @endif
</div>
<div class="mt-5">{{ (auth()->user()->role === 'student' ? $requests : $studentGroups)->links() }}</div>
@endsection
