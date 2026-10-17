@extends('layouts.app')

@section('title', 'Request Archives')

@section('content')
<h1 class="text-3xl font-bold text-slate-900">Request Archives</h1>
<p class="mt-2 text-slate-500">Saved request details, review history, and supporting documents.</p>
<div class="mt-6 divide-y rounded-2xl border bg-white">
    @forelse($archives as $archive)
        <div class="flex flex-wrap items-center justify-between gap-4 p-4">
            <div><strong>{{ $archive->reference_number ?? 'Request #'.$archive->original_request_id }}</strong><p class="text-sm text-slate-500">{{ ucfirst(str_replace('_', ' ', $archive->status)) }} · Archived {{ $archive->created_at->format('M j, Y g:i A') }} by {{ $archive->archived_by_name }}</p></div>
            <div class="flex flex-wrap gap-4 text-sm font-semibold"><a class="text-[#245B8E] underline" href="{{ route('admin.request-archives.export', $archive) }}">Download records</a>
                @foreach($archive->files as $index => $file)
                    @if($file['archive_path'])<a class="text-[#245B8E] underline" href="{{ route('admin.request-archives.file', [$archive, $index]) }}">{{ $file['original_name'] }}</a>@endif
                @endforeach
            </div>
        </div>
    @empty
        <p class="p-6 text-slate-500">No archived requests.</p>
    @endforelse
</div>
<div class="mt-4">{{ $archives->links() }}</div>
@endsection
