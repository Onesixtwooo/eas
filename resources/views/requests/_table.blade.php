@php
    $studentView = auth()->user()->role === 'student';
@endphp
<div class="request-table-wrap overflow-x-auto">
<table class="request-table {{ $studentView ? 'request-table-student' : '' }} w-full min-w-[900px] table-fixed text-left text-sm">
    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
        <tr>
            <th class="w-44 px-5 py-4">Reference</th>
            @unless($studentView)<th class="w-52 px-5 py-4">Student</th>@endunless
            <th class="w-40 px-5 py-4">Dates</th>
            <th class="px-5 py-4">Covered classes</th>
            <th class="w-36 px-5 py-4">Status</th>
            <th class="w-24 px-5 py-4"><span class="sr-only">Action</span></th>
        </tr>
    </thead>
    <tbody class="divide-y">
        @forelse($items as $item)
            @php
                $rowSubjects = $item->subjects->isNotEmpty() ? $item->subjects : collect([$item->subject]);
                $rowFacilitators = $item->facilitators->isNotEmpty() ? $item->facilitators : collect([$item->facilitator]);
            @endphp
            <tr class="hover:bg-slate-50">
                <td class="request-col-ref px-5 py-4 align-top font-semibold text-[#123A63]"><span class="break-words">{{ $item->reference_number ?? 'Pending' }}</span></td>
                @unless($studentView)<td class="request-col-student px-5 py-4 align-top"><b class="block text-slate-800">{{ $item->student->user->name }}</b><span class="mt-1 block text-xs text-slate-500">{{ $item->student->student_number }}</span></td>@endunless
                <td class="request-col-dates px-5 py-4 align-top">
                    <span class="block font-medium text-slate-800">Absent {{ $item->absence_date->format('M d, Y') }}</span>
                    <span class="mt-1 block text-xs text-slate-500">Submitted {{ $item->submitted_at?->format('M d, Y') ?? '—' }}</span>
                </td>
                <td class="request-col-classes px-5 py-4 align-top">
                    <button type="button" onclick="document.getElementById('covered-classes-{{ $item->id }}').showModal()" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 font-semibold text-[#123A63] hover:border-slate-300 hover:bg-white">
                        {{ $rowSubjects->count() }} {{ Str::plural('class', $rowSubjects->count()) }}
                        <span aria-hidden="true">→</span>
                    </button>
                    <dialog id="covered-classes-{{ $item->id }}" class="m-auto w-[min(38rem,calc(100%-2rem))] rounded-2xl border border-slate-200 p-0 text-left shadow-2xl backdrop:bg-slate-900/40">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4 border-b pb-4">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">Covered classes</h2>
                                    <p class="mt-1 text-sm text-slate-500">{{ $item->reference_number ?? 'Pending request' }} · {{ $item->student->user->name }}</p>
                                </div>
                                <button type="button" onclick="document.getElementById('covered-classes-{{ $item->id }}').close()" class="text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="Close">&times;</button>
                            </div>
                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                @foreach($rowSubjects as $subject)
                                    @php
                                        $rowFacilitator = $item->subjects->isNotEmpty()
                                            ? $rowFacilitators->firstWhere('id', $subject->pivot->facilitator_id)
                                            : $item->facilitator;
                                    @endphp
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                        <b class="block text-[#123A63]">{{ $subject->code }}</b>
                                        <span class="mt-1 block text-sm text-slate-600">{{ $subject->name }}</span>
                                        <span class="mt-2 block text-xs text-slate-500">{{ $rowFacilitator?->display_name ?? 'Instructor not assigned' }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-6 flex justify-end border-t pt-4">
                                <button type="button" onclick="document.getElementById('covered-classes-{{ $item->id }}').close()" class="rounded-xl bg-[#123A63] px-5 py-2.5 font-semibold text-white">Close</button>
                            </div>
                        </div>
                    </dialog>
                </td>
                <td class="request-col-status px-5 py-4 align-top">@include('requests._badge', ['status' => $item->status])</td>
                <td class="request-col-action px-5 py-4 align-top text-right"><a class="inline-flex rounded-lg border border-slate-200 px-3 py-2 font-semibold text-[#245B8E] hover:bg-white" href="{{ route('requests.show', $item) }}">View</a></td>
            </tr>
        @empty
            <tr><td colspan="{{ $studentView ? 5 : 6 }}" class="px-6 py-16 text-center text-slate-400">{{ $studentView ? 'You have no excuse requests yet.' : 'No excuse requests found.' }}</td></tr>
        @endforelse
    </tbody>
</table>
</div>

<style>
    @media (max-width: 767px) {
        .request-table-wrap {
            overflow: visible;
            background: #f8fafc;
        }
        .request-table {
            display: block;
            min-width: 0;
        }
        .request-table thead {
            display: none;
        }
        .request-table tbody {
            display: grid;
            gap: .75rem;
            padding: .75rem;
        }
        .request-table tbody tr {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            overflow: hidden;
            border: 1px solid #cbd5e1;
            border-radius: .85rem;
            background: #fff;
        }
        .request-table tbody td {
            display: block;
            min-width: 0;
            padding: .8rem;
            border: 0;
        }
        .request-table tbody td::before {
            display: block;
            margin-bottom: .35rem;
            color: #64748b;
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .request-col-ref::before { content: "Reference"; }
        .request-col-student::before { content: "Student"; }
        .request-col-dates::before { content: "Dates"; }
        .request-col-classes::before { content: "Covered classes"; }
        .request-col-status::before { content: "Status"; }
        .request-col-action::before { content: "Action"; }
        .request-col-dates,
        .request-col-classes {
            grid-column: 1 / -1;
            border-top: 1px solid #e2e8f0 !important;
        }
        .request-col-status,
        .request-col-action {
            border-top: 1px solid #e2e8f0 !important;
        }
        .request-col-action {
            text-align: right;
        }
        .request-col-action::before {
            text-align: right;
        }
        .request-table-student .request-col-ref,
        .request-table td[colspan] {
            grid-column: 1 / -1;
        }
        .request-table dialog {
            position: fixed;
        }
    }
</style>
