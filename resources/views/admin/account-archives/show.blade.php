@extends('layouts.app')

@section('title', 'Account Archive')

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4">
    <div><a href="{{ route('admin.account-archives.index') }}" class="text-sm font-semibold text-[#245B8E]">← Account Archives</a><h1 class="mt-2 text-3xl font-bold text-slate-900">{{ $archive->account_name }}</h1><p class="mt-1 text-slate-500">{{ $archive->account_email }} · Former ID {{ $archive->original_user_id }}</p></div>
    <a href="{{ route('admin.account-archives.export', $archive) }}" class="rounded-xl bg-[#123A63] px-4 py-3 font-semibold text-white">Download records (JSON)</a>
</div>
<div class="mt-6 grid gap-4 rounded-2xl border bg-white p-5 text-sm sm:grid-cols-3">
    <div><span class="block text-slate-500">Archived</span><strong>{{ $archive->created_at->format('M j, Y g:i A') }}</strong></div>
    <div><span class="block text-slate-500">By</span><strong>{{ $archive->archived_by_name }}</strong></div>
    <div><span class="block text-slate-500">Outcome</span><strong>{{ ucfirst($archive->outcome) }}</strong></div>
</div>
<section class="mt-6 rounded-2xl border bg-white p-5">
    <h2 class="text-xl font-bold text-slate-900">Saved records</h2>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($visibleRecords as $table => $rows)
            @php($count = $rows === null ? 0 : (in_array($table, ['user', 'student', 'faculty']) ? 1 : count($rows)))
            <div class="rounded-lg bg-slate-50 px-4 py-3"><span class="block text-sm text-slate-600">{{ ucwords(str_replace('_', ' ', $table)) }}</span><strong>{{ $count }}</strong></div>
        @endforeach
    </div>
    <p class="mt-4 text-sm text-slate-500">Download the JSON file to inspect original fields and relationships. Passwords and sign-in secrets are excluded.</p>
</section>
<section class="mt-6 rounded-2xl border bg-white p-5">
    <h2 class="text-xl font-bold text-slate-900">Saved files</h2>
    <div class="mt-4 divide-y">
        @forelse($archive->files as $index => $file)
            <div class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"><div><strong class="block">{{ basename($file['original_path']) }}</strong><span class="text-slate-500">{{ ucwords(str_replace('_', ' ', $file['kind'])) }} · Record {{ $file['record_id'] }}</span></div>
                @if($file['archive_path'])<a href="{{ route('admin.account-archives.file', [$archive, $index]) }}" class="font-semibold text-[#245B8E] underline">Download</a>@else<span class="text-red-700">Original file was missing when archived</span>@endif
            </div>
        @empty
            <p class="text-sm text-slate-500">No files were linked to this account.</p>
        @endforelse
    </div>
</section>
@endsection
