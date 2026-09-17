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
            @php($subjects = $item->subjects->isNotEmpty() ? $item->subjects : collect([$item->subject]))
            @php($facilitators = $item->facilitators->isNotEmpty() ? $item->facilitators : collect([$item->facilitator]))

            <div class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white">✓</span>
                <div>
                    <b class="block text-sm">VALID OFFICIAL SLIP</b>
                    <span class="text-xs text-emerald-700">Verified by the BSIT Department College Portal</span>
                </div>
            </div>

            <dl class="grid gap-5 sm:grid-cols-2">
                @foreach([
                    ['Reference number', $item->reference_number],
                    ['Student name', $item->student->user->name],
                    ['Date of absence', $item->absence_date->format('F d, Y')],
                    ['Subjects', $subjects->pluck('code')->join(', ')],
                    ['Course facilitators', $facilitators->pluck('user.name')->filter()->unique()->join(', ')],
                    ['Current status', ucwords(str_replace('_', ' ', $item->status))],
                    ['Date issued', $item->approved_at?->format('F d, Y') ?? '—'],
                    ['Issued by', 'BSIT Program Head']
                ] as [$k, $v])
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $k }}</dt>
                        <dd class="mt-1 font-semibold text-slate-800">{{ $v }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-8 border-t border-slate-100 pt-6">
                <a href="{{ auth()->check() ? (auth()->user()->role === 'student' ? route('requests.index') : route('dashboard')) : route('login') }}" class="block text-center text-sm font-semibold text-[#123A63] hover:underline">
                    ← Return to Portal
                </a>
            </div>
        </div>
    </main>
</body>
</html>

