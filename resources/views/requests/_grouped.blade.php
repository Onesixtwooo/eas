<div class="divide-y divide-slate-200">
    @forelse($groups as $student)
        @php
            $pendingCount = $student->pending_requests_count;
            $approvedCount = $student->approved_requests_count;
        @endphp
        <details class="group" @if($loop->first) open @endif>
            <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-5 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600 sm:gap-4 sm:px-5">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-800">{{ Str::upper(Str::substr($student->user->name, 0, 1)) }}</span>
                <span class="min-w-0 flex-1">
                    @if(in_array(auth()->user()->role, ['admin', 'program_head']))
                        <a href="{{ route('admin.students.show', $student) }}#request-history" class="block truncate text-base font-bold text-slate-900 hover:text-[#245B8E] hover:underline" title="View student information and request history">{{ $student->user->name }}</a>
                    @else
                        <b class="block truncate text-base text-slate-900">{{ $student->user->name }}</b>
                    @endif
                    <span class="mt-0.5 block text-sm text-slate-500">{{ $student->student_number }}</span>
                    <span class="mt-1 block text-xs text-slate-500 sm:hidden">{{ $student->requests_count }} total &middot; {{ $pendingCount }} pending</span>
                </span>
                <span class="hidden text-right text-sm text-slate-500 sm:block"><b class="block text-slate-800">{{ $student->requests_count }} {{ Str::plural('request', $student->requests_count) }} total</b><span>{{ $pendingCount }} pending &middot; {{ $approvedCount }} approved</span></span>
                @if($pendingCount)<span class="hidden rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800 md:inline-flex">{{ $pendingCount }} pending</span>@endif
                <span class="text-xl text-slate-400 transition-transform group-open:rotate-180" aria-hidden="true">⌄</span>
            </summary>

            <div class="border-t bg-slate-50/70 p-3 sm:p-5">
                <div class="space-y-3 md:hidden">
                    @foreach($student->requests as $item)
                        @php($rowSubjects = $item->subjects->isNotEmpty() ? $item->subjects : collect([$item->subject]))
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0"><p class="break-words text-sm font-bold text-[#123A63]">{{ $item->reference_number ?? 'Awaiting reference' }}</p><p class="mt-1 text-xs text-slate-500">Submitted {{ $item->submitted_at?->format('M d, Y') ?? 'Not submitted' }}</p></div>
                                <div class="shrink-0">@include('requests._badge', ['status' => $item->status])</div>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-4 border-y border-slate-100 py-4 text-sm">
                                <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Absence date</dt><dd class="mt-1 font-semibold text-slate-800">{{ $item->absence_date->format('M d, Y') }}</dd></div>
                                <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Covered classes</dt><dd class="mt-1"><button type="button" onclick="document.getElementById('covered-classes-{{ $item->id }}').showModal()" class="font-semibold text-[#245B8E]">{{ $rowSubjects->count() }} {{ Str::plural('class', $rowSubjects->count()) }} &rarr;</button></dd></div>
                            </dl>
                            <a href="{{ route('requests.show', $item) }}" class="mt-4 block w-full rounded-xl bg-[#123A63] px-4 py-2.5 text-center text-sm font-semibold text-white">View Request</a>
                        </article>
                    @endforeach
                </div>

                <div class="hidden overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Absence date</th><th class="px-4 py-3">Covered classes</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"><span class="sr-only">Action</span></th></tr></thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach($student->requests as $item)
                                @php($rowSubjects = $item->subjects->isNotEmpty() ? $item->subjects : collect([$item->subject]))
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-4 align-top font-semibold text-[#123A63]">{{ $item->reference_number ?? 'Awaiting reference' }}<span class="mt-1 block text-xs font-normal text-slate-500">Submitted {{ $item->submitted_at?->format('M d, Y') ?? 'Not submitted' }}</span></td>
                                    <td class="px-4 py-4 align-top font-medium text-slate-800">{{ $item->absence_date->format('M d, Y') }}</td>
                                    <td class="px-4 py-4 align-top"><button type="button" onclick="document.getElementById('covered-classes-{{ $item->id }}').showModal()" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-semibold text-[#123A63] hover:bg-white">{{ $rowSubjects->count() }} {{ Str::plural('class', $rowSubjects->count()) }} <span aria-hidden="true">&rarr;</span></button></td>
                                    <td class="px-4 py-4 align-top">@include('requests._badge', ['status' => $item->status])</td>
                                    <td class="px-4 py-4 text-right align-top"><a class="inline-flex rounded-lg border border-slate-200 px-3 py-2 font-semibold text-[#245B8E] hover:bg-white" href="{{ route('requests.show', $item) }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @foreach($student->requests as $item)
                    @php($rowSubjects = $item->subjects->isNotEmpty() ? $item->subjects : collect([$item->subject]))
                    @php($rowFacilitators = $item->facilitators->isNotEmpty() ? $item->facilitators : collect([$item->facilitator]))
                    <dialog id="covered-classes-{{ $item->id }}" onclick="if (event.target === this) this.close()" class="m-auto w-[min(38rem,calc(100%-2rem))] rounded-2xl border border-slate-200 p-0 text-left shadow-2xl backdrop:bg-slate-900/40">
                        <div class="p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4 border-b pb-4"><div><h2 class="text-xl font-bold text-slate-900">Covered classes</h2><p class="mt-1 text-sm text-slate-500">{{ $item->reference_number ?? 'Pending request' }} &middot; {{ $student->user->name }}</p></div><button type="button" onclick="document.getElementById('covered-classes-{{ $item->id }}').close()" class="text-2xl leading-none text-slate-400" aria-label="Close">&times;</button></div>
                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                @foreach($rowSubjects as $subject)
                                    @php($rowFacilitator = $item->subjects->isNotEmpty() ? $rowFacilitators->firstWhere('id', $subject->pivot->facilitator_id) : $item->facilitator)
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><b class="block text-[#123A63]">{{ $subject->code }}</b><span class="mt-1 block text-sm text-slate-600">{{ $subject->name }}</span><span class="mt-2 block text-xs text-slate-500">{{ $rowFacilitator?->display_name ?? 'Instructor not assigned' }}</span></div>
                                @endforeach
                            </div>
                            <div class="mt-6 flex justify-end border-t pt-4"><button type="button" onclick="document.getElementById('covered-classes-{{ $item->id }}').close()" class="rounded-xl bg-[#123A63] px-5 py-2.5 font-semibold text-white">Close</button></div>
                        </div>
                    </dialog>
                @endforeach
            </div>
        </details>
    @empty
        <div class="px-6 py-16 text-center text-slate-400">No excuse requests found.</div>
    @endforelse
</div>
