@extends('layouts.app')
@section('title', 'Faculty')
@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Administration</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Faculty</h1>
        <p class="mt-2 text-slate-500">Manage faculty portal access. Linked faculty can see excuse requests submitted for their assigned subjects.</p>
    </div>
    <a href="{{ route('admin.faculty.create') }}" class="rounded-xl bg-[#123A63] px-5 py-3 text-center font-semibold text-white hover:bg-[#245B8E]">+ Add Faculty Account</a>
</div>

<form method="get" class="mt-7 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row">
    <input name="search" value="{{ request('search') }}" placeholder="Search faculty, employee number, or email..." class="min-w-0 flex-1">
    <button class="rounded-xl bg-[#123A63] px-6 py-3 font-semibold text-white sm:py-0">Search</button>
</form>

<div class="mt-5 space-y-3 sm:hidden">
    @forelse($faculty as $member)
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-semibold text-slate-900">{{ $member->display_name }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $member->employee_number ?: 'No employee number' }}</p>
                </div>
                @if($member->user_id)
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $member->user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">{{ $member->user->is_active ? 'Can log in' : 'Disabled' }}</span>
                @endif
            </div>

            <dl class="mt-4 space-y-3 border-y border-slate-100 py-4 text-sm">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Designation</dt>
                    <dd class="mt-1 text-slate-700">{{ $member->designation }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Portal account</dt>
                    <dd class="mt-1 break-all text-slate-700">{{ $member->user_id ? $member->user->email : 'No login account' }}</dd>
                </div>
            </dl>

            <div class="mt-4 flex gap-3">
                @unless($member->user_id)
                    <a href="{{ route('admin.faculty.create', ['faculty_id' => $member->id]) }}" class="flex-1 rounded-xl bg-[#123A63] px-4 py-2.5 text-center text-sm font-semibold text-white">Create account</a>
                @endunless
                <a href="{{ route('admin.faculty.edit', $member) }}" class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-[#245B8E] hover:bg-blue-50">Edit</a>
            </div>
        </article>
    @empty
        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center text-slate-500 shadow-sm">No faculty found.</div>
    @endforelse
</div>

<div class="mt-5 hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm sm:block">
    <table class="w-full min-w-[680px] text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-4">Faculty</th><th class="px-5 py-4">Designation</th><th class="px-5 py-4">Portal account</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Actions</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($faculty as $member)
            <tr>
                <td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $member->display_name }}</p><p class="text-xs text-slate-500">{{ $member->employee_number ?: 'No employee number' }}</p></td>
                <td class="px-5 py-4 text-slate-600">{{ $member->designation }}</td>
                <td class="px-5 py-4">@if($member->user_id)<p>{{ $member->user->email }}</p>@else<span class="text-slate-400">No login account</span>@endif</td>
                <td class="px-5 py-4">@if($member->user_id)<span class="rounded-full px-3 py-1 text-xs font-bold {{ $member->user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">{{ $member->user->is_active ? 'Can log in' : 'Disabled' }}</span>@else<a href="{{ route('admin.faculty.create', ['faculty_id' => $member->id]) }}" class="font-semibold text-[#245B8E]">Create account</a>@endif</td>
                <td class="px-5 py-4 text-right"><a href="{{ route('admin.faculty.edit', $member) }}" class="inline-block rounded-lg border px-3 py-2 font-semibold text-[#245B8E] hover:bg-blue-50">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-6 py-16 text-center text-slate-500">No faculty found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $faculty->links() }}</div>
@endsection
