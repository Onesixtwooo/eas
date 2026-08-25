@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
    $facultyDashboard = $u->role === 'faculty';
    $pendingReview = ($counts['submitted'] ?? 0) + ($counts['under_review'] ?? 0);
    $needsAttention = ($counts['returned'] ?? 0) + ($counts['rejected'] ?? 0);
    $completedCount = $counts['completed'] ?? 0;
    $approvedCount = $counts['approved'] ?? 0;
    $acknowledgedCount = $counts['acknowledged'] ?? 0;
    $cards = $facultyDashboard
        ? [
            ['Confirmed Requests', $totalRequests, 'All requests assigned to you', 'document', 'bg-blue-50 text-blue-700'],
            ['Assigned Students', $assignedStudents, 'Students covered by confirmed slips', 'users', 'bg-indigo-50 text-indigo-700'],
            ['Approved Requests', $approvedCount, 'Ready for instructor handling', 'check', 'bg-teal-50 text-teal-700'],
            ['Completed', $completedCount, 'Requests fully processed', 'check', 'bg-emerald-50 text-emerald-700'],
        ]
        : [
            ['Total Requests', $totalRequests, 'All recorded excuse requests', 'document', 'bg-blue-50 text-blue-700'],
            ['Under Review', $pendingReview, 'Waiting for an administrative decision', 'clock', 'bg-amber-50 text-amber-700'],
            ['Approved', $approvedCount, 'Approved and ready for presentation', 'check', 'bg-emerald-50 text-emerald-700'],
            ['Needs Attention', $needsAttention, 'Returned or rejected requests', 'alert', 'bg-red-50 text-red-700'],
        ];
    $statusRows = $facultyDashboard
        ? [['Approved', $approvedCount, 'bg-blue-500'], ['Acknowledged', $acknowledgedCount, 'bg-violet-500'], ['Completed', $completedCount, 'bg-emerald-500']]
        : [['Submitted', $counts['submitted'] ?? 0, 'bg-blue-500'], ['Under review', $counts['under_review'] ?? 0, 'bg-amber-500'], ['Approved', $approvedCount, 'bg-emerald-500'], ['Returned', $counts['returned'] ?? 0, 'bg-orange-500'], ['Rejected', $counts['rejected'] ?? 0, 'bg-red-500'], ['Completed', $completedCount, 'bg-violet-500']];
    $actionRows = $facultyDashboard
        ? [['Approved requests', $approvedCount, 'Ready for you to review', 'bg-blue-100 text-blue-700'], ['Acknowledged', $acknowledgedCount, 'Currently being handled', 'bg-violet-100 text-violet-700'], ['Completed', $completedCount, 'Finished request records', 'bg-emerald-100 text-emerald-700']]
        : [['New submissions', $counts['submitted'] ?? 0, 'Ready to start review', 'bg-blue-100 text-blue-700'], ['In review', $counts['under_review'] ?? 0, 'Waiting for a decision', 'bg-amber-100 text-amber-700'], ['Returned or rejected', $needsAttention, 'May require follow-up', 'bg-red-100 text-red-700']];
    $firstKey = $facultyDashboard ? 'confirmed' : 'late';
    $secondKey = $facultyDashboard ? 'completed' : 'excused';
@endphp

<section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#123A63] via-[#174f78] to-[#17613a] px-6 py-7 text-white shadow-lg sm:px-8 sm:py-9">
    <div class="absolute -right-16 -top-20 size-64 rounded-full border-[36px] border-white/5"></div>
    <div class="absolute -bottom-24 right-36 size-52 rounded-full bg-white/5"></div>
    <div class="relative flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
        <div><p class="text-xs font-bold uppercase tracking-[.2em] text-blue-200">{{ now()->format('l, F j, Y') }}</p><h1 class="mt-3 text-3xl font-bold sm:text-4xl">Good day, {{ explode(' ', trim($u->name))[0] }}!</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-blue-100 sm:text-base">{{ $facultyDashboard ? 'Track confirmed student absences assigned to your subjects and keep every request moving.' : 'Review priorities, monitor request activity, and manage academic services from one place.' }}</p></div>
        <a href="{{ route('requests.index') }}" class="inline-flex shrink-0 items-center justify-center gap-2 self-start rounded-xl bg-white px-5 py-3 text-sm font-bold text-[#123A63] shadow-sm transition hover:bg-blue-50 lg:self-auto">Open requests <span aria-hidden="true">&rarr;</span></a>
    </div>
</section>

<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Dashboard summary">
    @foreach($cards as [$title, $number, $description, $icon, $color])
        <article class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-start justify-between gap-4"><div><p class="text-sm font-semibold text-slate-500">{{ $title }}</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($number) }}</p></div><span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $color }}">
                @if($icon === 'users')<svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                @elseif($icon === 'check')<svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="2.2"><path d="m5 12 4 4L19 6"/></svg>
                @elseif($icon === 'clock')<svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                @elseif($icon === 'alert')<svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="2"><path d="M12 3 2 21h20L12 3Z"/><path d="M12 9v5M12 18h.01"/></svg>
                @else<svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="2"><path d="M6 2h9l4 4v16H6z"/><path d="M14 2v5h5M9 12h6M9 16h6"/></svg>@endif
            </span></div><p class="mt-4 text-xs leading-5 text-slate-400">{{ $description }}</p>
        </article>
    @endforeach
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(18rem,.75fr)]">
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 p-5 sm:p-6"><div><h2 class="font-bold text-slate-900">{{ $facultyDashboard ? 'Request Progress' : 'Action Required' }}</h2><p class="mt-1 text-sm text-slate-500">{{ $facultyDashboard ? 'Current workload for your assigned requests' : 'Priorities that may need your attention' }}</p></div><a href="{{ route('requests.index') }}" class="shrink-0 text-sm font-semibold text-[#245B8E]">View all</a></div>
        <div class="divide-y divide-slate-100">@foreach($actionRows as [$label, $number, $description, $badgeColor])<a href="{{ route('requests.index') }}" class="flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50 sm:px-6"><span class="grid size-11 shrink-0 place-items-center rounded-xl text-sm font-bold {{ $badgeColor }}">{{ $number }}</span><span class="min-w-0 flex-1"><b class="block text-sm text-slate-800">{{ $label }}</b><span class="mt-0.5 block text-xs text-slate-500">{{ $description }}</span></span><span class="text-slate-300" aria-hidden="true">&rarr;</span></a>@endforeach</div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="font-bold text-slate-900">Quick Actions</h2><p class="mt-1 text-sm text-slate-500">Go directly to common tasks</p>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
            <a href="{{ route('requests.index') }}" class="dashboard-action"><span>Manage requests</span><span>&rarr;</span></a>
            @if($facultyDashboard)
                <a href="{{ route('student-reports.index') }}" class="dashboard-action"><span>Student report</span><span>&rarr;</span></a>
                <a href="{{ route('profile') }}" class="dashboard-action"><span>View profile</span><span>&rarr;</span></a>
            @else
                <a href="{{ route('admin.students.index') }}" class="dashboard-action"><span>Manage students</span><span>&rarr;</span></a>
                <a href="{{ route('admin.faculty.index') }}" class="dashboard-action"><span>Manage faculty</span><span>&rarr;</span></a>
                <a href="{{ route('admin.instructor-assignments.index') }}" class="dashboard-action"><span>Instructor assignments</span><span>&rarr;</span></a>
            @endif
        </div>
    </section>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(18rem,.5fr)]">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" x-data="{ period: 'month', datasets: @js($analyticsByPeriod), firstKey: '{{ $firstKey }}', secondKey: '{{ $secondKey }}', descriptions: { day: '{{ $facultyDashboard ? 'Confirmed request activity for the last seven days' : 'Unique students per day for the last seven days' }}', week: '{{ $facultyDashboard ? 'Confirmed request activity for the last six weeks' : 'Unique students per week for the last six weeks' }}', month: '{{ $facultyDashboard ? 'Confirmed request activity for the last six months' : 'Unique students per month for the last six months' }}' }, get buckets() { return this.datasets[this.period] }, get maximum() { return Math.max(1, ...this.buckets.flatMap(bucket => [bucket[this.firstKey], bucket[this.secondKey]])) }, height(value) { return value ? Math.max(8, (value / this.maximum) * 100) : 2 } }">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"><div><h2 class="font-bold text-slate-900">{{ $facultyDashboard ? 'Confirmed Request Activity' : 'Student Excuse Analytics' }}</h2><p class="mt-1 text-sm text-slate-500" x-text="descriptions[period]"></p></div><div class="inline-flex self-start rounded-lg bg-slate-100 p-1" aria-label="Analytics period">@foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label)<button type="button" @click="period = '{{ $value }}'" :aria-pressed="period === '{{ $value }}'" :class="period === '{{ $value }}' ? 'bg-white text-[#245B8E] shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="rounded-md px-3 py-1.5 text-xs font-semibold transition">{{ $label }}</button>@endforeach</div></div>
        <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-xs font-semibold text-slate-600"><span class="flex items-center gap-2"><i class="size-3 rounded-sm {{ $facultyDashboard ? 'bg-blue-500' : 'bg-orange-400' }}"></i>{{ $facultyDashboard ? 'Confirmed requests' : 'Late Students' }}</span><span class="flex items-center gap-2"><i class="size-3 rounded-sm bg-emerald-500"></i>{{ $facultyDashboard ? 'Completed requests' : 'Excused Students' }}</span></div>
        <div class="mt-7 flex h-64 gap-2 border-b border-slate-200 sm:gap-5"><template x-for="bucket in buckets" :key="period + bucket.label"><div class="flex min-w-0 flex-1 flex-col justify-end"><div class="flex h-52 items-end justify-center gap-1 sm:gap-2"><div class="relative w-4 rounded-t-md {{ $facultyDashboard ? 'bg-blue-500' : 'bg-orange-400' }} sm:w-7" :style="`height: ${height(bucket[firstKey])}%`"><span class="absolute -top-6 left-1/2 -translate-x-1/2 text-[10px] font-bold text-slate-600 sm:text-xs" x-text="bucket[firstKey]"></span></div><div class="relative w-4 rounded-t-md bg-emerald-500 sm:w-7" :style="`height: ${height(bucket[secondKey])}%`"><span class="absolute -top-6 left-1/2 -translate-x-1/2 text-[10px] font-bold text-slate-600 sm:text-xs" x-text="bucket[secondKey]"></span></div></div><p class="truncate py-3 text-center text-[10px] font-semibold text-slate-500 sm:text-xs" x-text="bucket.label"></p></div></template></div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="font-bold text-slate-900">Status Overview</h2><p class="mt-1 text-sm text-slate-500">Distribution of request records</p><div class="mt-6 space-y-5">@foreach($statusRows as [$label, $number, $barColor])@php($percentage = $totalRequests ? min(100, round(($number / $totalRequests) * 100)) : 0)<div><div class="mb-2 flex items-center justify-between text-sm"><span class="font-medium text-slate-600">{{ $label }}</span><b class="text-slate-900">{{ $number }}</b></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $barColor }}" style="width: {{ $percentage }}%"></div></div></div>@endforeach</div></section>
</div>

<section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-4 border-b p-5 sm:p-6">
        <div><h2 class="font-bold text-slate-900">{{ $facultyDashboard ? 'Recent Approved Activity' : 'Recent Requests' }}</h2><p class="mt-1 text-sm text-slate-500">{{ $facultyDashboard ? 'Students with recently approved requests' : 'Students with the latest request activity' }}</p></div>
        <a href="{{ route('requests.index') }}" class="shrink-0 text-sm font-semibold text-[#245B8E]">View all <span aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        @forelse($requests as $item)
            <a href="{{ route('requests.show', $item) }}" class="group flex min-w-0 items-center gap-3 bg-white p-5 transition hover:bg-slate-50">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-emerald-100 font-bold text-emerald-800">{{ Str::upper(Str::substr($item->student->user->name, 0, 1)) }}</span>
                <span class="min-w-0 flex-1 truncate font-semibold text-slate-800 group-hover:text-[#245B8E]">{{ $item->student->user->name }}</span>
                <span class="shrink-0 text-slate-300 group-hover:text-[#245B8E]" aria-hidden="true">&rarr;</span>
            </a>
        @empty
            <p class="bg-white px-6 py-12 text-center text-slate-500 sm:col-span-2 xl:col-span-4">No recent request activity.</p>
        @endforelse
    </div>
</section>

<style>
    .dashboard-action { display:flex; align-items:center; justify-content:space-between; border:1px solid #e2e8f0; border-radius:.75rem; padding:.75rem 1rem; color:#334155; font-size:.875rem; font-weight:600; }
    .dashboard-action:hover { border-color:#bfdbfe; background:#eff6ff; color:#123A63; }
</style>
@endsection
