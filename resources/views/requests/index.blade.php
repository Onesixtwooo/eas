@extends('layouts.app')
@section('title', auth()->user()->role === 'student' ? 'My Excuse Requests' : 'Excuse Requests')
@section('content')
<div class="flex items-end justify-between">
    <div>
        <h1 class="text-3xl font-bold text-slate-900">{{ auth()->user()->role === 'student' ? 'My Excuse Requests' : 'Excuse Requests' }}</h1>
        <p class="mt-2 text-slate-500">{{ auth()->user()->role === 'student' ? 'View and track excuse requests submitted from your account.' : 'Search, filter, and track requests.' }}</p>
    </div>
    @if(auth()->user()->role === 'student')
        <a href="{{ route('requests.create') }}" class="rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white hover:bg-emerald-700">+ New request</a>
    @endif
</div>
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
