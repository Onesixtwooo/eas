@extends('layouts.app')

@section('title', 'Account Archives')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div><p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Administration</p><h1 class="mt-1 text-3xl font-bold text-slate-900">Account Archives</h1><p class="mt-2 text-slate-500">Records saved before an account was removed or disabled.</p></div>
    <a href="{{ route('admin.accounts.index') }}" class="rounded-xl border px-4 py-2 font-semibold text-[#123A63]">Back to accounts</a>
</div>
<form method="get" class="mt-6 flex gap-2 rounded-xl border bg-white p-3">
    <input name="search" value="{{ request('search') }}" placeholder="Search name, email, or former ID" class="min-w-0 flex-1">
    <button class="rounded-lg bg-[#123A63] px-5 py-2 font-semibold text-white">Search</button>
</form>
<div class="mt-5 overflow-x-auto rounded-2xl border bg-white shadow-sm">
    <table class="w-full min-w-[650px] text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-4">Account</th><th class="px-5 py-4">Role</th><th class="px-5 py-4">Outcome</th><th class="px-5 py-4">Archived</th><th class="px-5 py-4 text-right">Records</th></tr></thead>
        <tbody class="divide-y">
            @forelse($archives as $archive)
                <tr><td class="px-5 py-4"><strong class="block">{{ $archive->account_name }}</strong><span class="text-slate-500">{{ $archive->account_email }}</span></td><td class="px-5 py-4">{{ ucwords(str_replace('_', ' ', $archive->account_role)) }}</td><td class="px-5 py-4">{{ ucfirst($archive->outcome) }}</td><td class="px-5 py-4">{{ $archive->created_at->format('M j, Y g:i A') }}</td><td class="px-5 py-4 text-right"><a href="{{ route('admin.account-archives.show', $archive) }}" class="font-semibold text-[#245B8E] underline">View archive</a></td></tr>
            @empty
                <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No account archives found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $archives->links() }}</div>
@endsection
