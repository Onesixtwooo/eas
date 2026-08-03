@php($studentView = auth()->user()->role === 'student')
<table class="w-full min-w-[850px] text-left text-sm">
    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
        <tr><th class="px-5 py-4">Reference</th><th class="px-5 py-4">Absence</th><th class="px-5 py-4">{{ $studentView ? 'Subject' : 'Student / Subject' }}</th><th class="px-5 py-4">Facilitator</th><th class="px-5 py-4">Submitted</th><th class="px-5 py-4">Status</th><th class="px-5 py-4"></th></tr>
    </thead>
    <tbody class="divide-y">
        @forelse($items as $item)
            <tr class="hover:bg-slate-50">
                <td class="px-5 py-4 font-semibold text-[#123A63]">{{ $item->reference_number ?? 'Pending' }}</td>
                <td class="px-5 py-4">{{ $item->absence_date->format('M d, Y') }}</td>
                <td class="px-5 py-4"><b class="block">{{ $item->subject->code }}</b>@unless($studentView)<span class="text-xs text-slate-500">{{ $item->student->user->name }}</span>@endunless</td>
                <td class="px-5 py-4">{{ $item->facilitator->user->name }}</td>
                <td class="px-5 py-4">{{ $item->submitted_at?->format('M d, Y') ?? '—' }}</td>
                <td class="px-5 py-4">@include('requests._badge', ['status' => $item->status])</td>
                <td class="px-5 py-4"><a class="font-semibold text-[#245B8E]" href="{{ route('requests.show', $item) }}">View →</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-6 py-16 text-center text-slate-400">{{ $studentView ? 'You have no excuse requests yet.' : 'No excuse requests found.' }}</td></tr>
        @endforelse
    </tbody>
</table>
