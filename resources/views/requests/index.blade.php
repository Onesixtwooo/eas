@extends('layouts.app')
@section('title', auth()->user()->role === 'student' ? 'My Excuse Requests' : 'Excuse Requests')
@section('content')
<div class="flex items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold text-slate-900">{{ auth()->user()->role === 'student' ? 'My Excuse Requests' : 'Excuse Requests' }}</h1>
        <p class="mt-2 text-slate-500">{{ auth()->user()->role === 'student' ? 'View and track excuse requests submitted from your account.' : 'Search, filter, and track requests.' }}</p>
    </div>
    @if(auth()->user()->role === 'student')
        <a href="{{ route('requests.create') }}" class="rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white hover:bg-emerald-700">+ New request</a>
    @elseif(in_array(auth()->user()->role, ['admin', 'program_head']))
        <button type="button" onclick="document.getElementById('slip-settings').showModal()" class="rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold text-[#123A63] shadow-sm hover:bg-slate-50">Settings</button>
    @endif
</div>
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
    <input name="search" value="{{ request('search') }}" placeholder="{{ auth()->user()->role === 'student' ? 'Search reference or subject…' : 'Search reference, student, or subject…' }}">
    <select name="status">
        <option value="">All statuses</option>
        @foreach(['draft','submitted','under_review','returned','approved','rejected','acknowledged','completed'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
        @endforeach
    </select>
    <button class="rounded-xl bg-[#123A63] px-6 font-semibold text-white">Filter</button>
</form>
<div class="mt-5 overflow-hidden rounded-2xl border bg-white shadow-sm">
    @include('requests._table', ['items' => $requests])
</div>
<div class="mt-5">{{ $requests->links() }}</div>
@endsection
