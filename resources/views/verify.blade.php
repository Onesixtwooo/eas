<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Slip — OLSHCO EAS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="grid min-h-screen place-items-center p-5 bg-slate-50">
    <main class="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-xl border border-slate-100">
        <header class="bg-[#123A63] p-7 text-white">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images.jpg') }}" alt="OLSHCO logo" class="size-10 rounded-full bg-white object-cover">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-200">OLSHCO Official Verification</p>
                    <h1 class="text-xl font-bold">Excuse & Admission Slip</h1>
                </div>
            </div>
        </header>

        <div class="p-7">
            @php
                $subjects = $item->subjects->isNotEmpty() ? $item->subjects : collect([$item->subject]);
                $facilitators = $item->facilitators->isNotEmpty() ? $item->facilitators : collect([$item->facilitator]);
                $issued = $item->slipIssuance?->snapshot;
                $reconstructed = ($issued['snapshot_source'] ?? null) !== 'captured';
                $valid = in_array($item->status, ['approved', 'acknowledged', 'completed'], true);
                $latestAmendment = $item->slipAmendments->first();
            @endphp

            <div class="mb-6 flex items-center gap-3 rounded-xl border p-4 {{ $valid && ! $latestAmendment ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-300 bg-amber-50 text-amber-900' }}">
                <span class="grid size-7 shrink-0 place-items-center rounded-full text-sm font-bold text-white {{ $valid && ! $latestAmendment ? 'bg-emerald-600' : 'bg-amber-700' }}">✓</span>
                <div>
                    <b class="block text-sm">{{ ! $valid ? 'REVOKED OFFICIAL SLIP' : ($latestAmendment ? 'AMENDED OFFICIAL SLIP' : 'VALID OFFICIAL SLIP') }}</b>
                    <span class="text-xs">{{ ! $valid ? 'This slip is no longer valid.' : ($latestAmendment ? 'Check the current official result below.' : 'Verified by the BSIT Department College Portal') }}</span>
                </div>
            </div>

            @if($reconstructed)
                <p class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">Historical slip details were reconstructed from records available when history tracking began. They may differ from the original printed slip.</p>
            @endif

            @if($latestAmendment)
                <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                    <b class="block">Latest official amendment: {{ $latestAmendment->slip_remark }}</b>
                    <span class="mt-1 block">{{ $latestAmendment->remarks }}</span>
                    <span class="mt-1 block text-xs">Recorded {{ $latestAmendment->created_at->format('F d, Y g:i A') }}</span>
                </div>
            @endif

            <dl class="grid gap-5 sm:grid-cols-2">
                @foreach([
                    ['Reference number', $item->reference_number],
                    ['Student name', $issued['student_name'] ?? $item->student->user->name],
                    ['Attendance', ucfirst($issued['attendance_type'] ?? $item->attendance_type ?? 'absent')],
                    ['Date', $issued ? \Illuminate\Support\Carbon::parse($issued['absence_date'])->format('F d, Y') : $item->absence_date->format('F d, Y')],
                    ['Subjects', $issued ? implode(', ', $issued['subject_codes']) : $subjects->pluck('code')->join(', ')],
                    ['Course facilitators', $issued ? implode(', ', $issued['facilitator_names']) : $facilitators->pluck('user.name')->filter()->unique()->join(', ')],
                    ['Current status', ucwords(str_replace('_', ' ', $item->status))],
                    [$reconstructed ? 'Recorded initial result' : 'Original issued result', $issued['slip_remark'] ?? $item->slip_remark],
                    ['Current official result', $valid ? ($latestAmendment?->slip_remark ?? $issued['slip_remark'] ?? $item->slip_remark) : 'REVOKED'],
                    ['Date issued', $issued && $issued['issued_at'] ? \Illuminate\Support\Carbon::parse($issued['issued_at'])->format('F d, Y') : ($item->approved_at?->format('F d, Y') ?? '—')],
                    ['Issued by', 'BSIT Program Head']
                ] as [$k, $v])
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $k }}</dt>
                        <dd class="mt-1 font-semibold text-slate-800">{{ $v }}</dd>
                    </div>
                @endforeach
            </dl>

            @php
                $viewer = auth()->user();
                $isOwner = $viewer?->role === 'student' && $viewer->student?->id === $item->student_id;
                $canViewDocuments = $viewer && (
                    in_array($viewer->role, ['admin', 'program_head'], true)
                    || $isOwner
                    || ($viewer->role === 'faculty' && $viewer->faculty && (
                        $item->facilitator_id === $viewer->faculty->id
                        || $item->facilitators->contains('id', $viewer->faculty->id)
                    ))
                );
            @endphp
            <section class="mt-8 border-t border-slate-100 pt-6" aria-labelledby="supporting-documents-heading">
                <h2 id="supporting-documents-heading" class="text-base font-bold text-slate-800">Supporting documents</h2>

                @if(session('success'))
                    <p role="status" class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>
                @endif

                @if($canViewDocuments)
                    @if($item->documents->isNotEmpty())
                        <ul class="mt-4 flex flex-col gap-2">
                            @foreach($item->documents as $document)
                                <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 text-sm">
                                    <span class="min-w-0 break-all text-slate-700">{{ $document->original_name }}</span>
                                    <button type="button" onclick="document.getElementById('verification-document-{{ $document->id }}').showModal()" class="shrink-0 font-semibold text-[#123A63] hover:underline" aria-label="Preview {{ $document->original_name }}">View file</button>
                                    <dialog id="verification-document-{{ $document->id }}" onclick="if (event.target === this) this.close()" class="m-auto max-h-[calc(100vh-2rem)] w-[min(72rem,calc(100%-2rem))] overflow-hidden rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-950/80" aria-label="Preview {{ $document->original_name }}">
                                        <div class="flex max-h-[calc(100vh-2rem)] flex-col">
                                            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                                                <h3 class="min-w-0 truncate font-semibold text-slate-900">{{ $document->original_name }}</h3>
                                                <div class="flex shrink-0 items-center gap-3">
                                                    <a href="{{ route('requests.documents.show', $document) }}" target="_blank" rel="noopener" class="text-sm font-semibold text-[#123A63] hover:underline">Open separately</a>
                                                    <button type="button" onclick="document.getElementById('verification-document-{{ $document->id }}').close()" class="text-2xl leading-none text-slate-500" aria-label="Close preview">&times;</button>
                                                </div>
                                            </div>
                                            <div class="grid min-h-0 flex-1 place-items-center overflow-auto bg-slate-100 p-3">
                                                @if(str_starts_with($document->mime_type, 'image/'))
                                                    <img src="{{ route('requests.documents.show', $document) }}" alt="{{ $document->original_name }}" style="max-width:100%;max-height:calc(100vh - 9rem);object-fit:contain;">
                                                @elseif($document->mime_type === 'application/pdf')
                                                    <iframe src="{{ route('requests.documents.show', $document) }}" title="{{ $document->original_name }}" style="width:100%;height:min(70vh,700px);border:0;"></iframe>
                                                @else
                                                    <p class="p-6 text-center text-sm text-slate-600">Preview unavailable. Use Open separately to view this file.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </dialog>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-sm text-slate-500">No supporting documents attached yet.</p>
                    @endif
                @elseif($item->documents->isNotEmpty())
                    <p class="mt-2 text-sm text-slate-500">Supporting documents are attached. Sign in with an authorized portal account to view them.</p>
                @else
                    <p class="mt-2 text-sm text-slate-500">No supporting documents attached yet.</p>
                @endif

                <p class="mt-4 text-xs text-slate-500">Attachments are locked when the request is submitted.</p>
            </section>

            <div class="mt-8 border-t border-slate-100 pt-6">
                <a href="{{ auth()->check() ? (auth()->user()->role === 'student' ? route('requests.index') : route('dashboard')) : route('login') }}" class="block text-center text-sm font-semibold text-[#123A63] hover:underline">
                    ← Return to Portal
                </a>
            </div>
        </div>
    </main>
</body>
</html>

