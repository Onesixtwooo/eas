@extends('layouts.app') @section('title','Dashboard') @section('content')
<div class="flex flex-col justify-between gap-5 md:flex-row md:items-end"><div><p class="text-sm font-semibold text-[#245B8E]">{{ now()->format('l, F j, Y') }}</p><h1 class="mt-1 text-3xl font-bold text-slate-900">Good day, {{ explode(' ',$u->name)[0] }}!</h1><p class="mt-2 text-slate-500">{{ $u->role==='student'?'Submit and monitor your excuse and admission slip requests.':'Review, verify, and manage excuse requests from one place.' }}</p></div>@if($u->role==='student')<a href="{{ route('requests.create') }}" class="rounded-xl bg-[#B3262E] px-5 py-3 text-center font-semibold text-white shadow hover:bg-red-800">+ Submit New Excuse Slip</a>@endif</div>
@php($cards=[['Total Requests',$totalRequests,'All recorded requests','▤','bg-blue-50 text-blue-700'],['Late Students',$lateStudents,'Submitted after the absence date','⌛','bg-orange-50 text-orange-700'],['Excused Students',$excusedStudents,'Unique students with approved excused slips','✓','bg-teal-50 text-teal-700'],['Under Review',($counts['under_review']??0)+($counts['submitted']??0),'Awaiting a decision','◷','bg-amber-50 text-amber-700'],['Approved',$counts['approved']??0,'Ready for presentation','✓','bg-emerald-50 text-emerald-700'],['Returned or Rejected',($counts['returned']??0)+($counts['rejected']??0),'Needs your attention','!','bg-red-50 text-red-700']])
<div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">@foreach($cards as [$title,$number,$desc,$icon,$color])<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-start justify-between"><div><p class="text-sm font-medium text-slate-500">{{ $title }}</p><p class="mt-3 text-3xl font-bold text-slate-900">{{ $number }}</p></div><span class="grid size-11 place-items-center rounded-xl {{ $color }}">{{ $icon }}</span></div><p class="mt-3 text-xs text-slate-400">{{ $desc }}</p></div>@endforeach</div>
<section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" x-data="{ period: 'month', datasets: @js($analyticsByPeriod), descriptions: { day: 'Unique students per day for the last seven days', week: 'Unique students per week for the last six weeks', month: 'Unique students per month for the last six months' }, get buckets() { return this.datasets[this.period] }, get maximum() { return Math.max(1, ...this.buckets.flatMap(bucket => [bucket.late, bucket.excused])) }, height(value) { return value ? Math.max(8, (value / this.maximum) * 100) : 2 } }">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div><h2 class="font-bold text-slate-900">Student Excuse Analytics</h2><p class="mt-1 text-sm text-slate-500" x-text="descriptions[period]">Unique students per month for the last six months</p></div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="inline-flex self-start rounded-lg bg-slate-100 p-1" aria-label="Analytics period">
                @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label)
                    <button type="button" @click="period = '{{ $value }}'" :aria-pressed="period === '{{ $value }}'" :class="period === '{{ $value }}' ? 'bg-white text-[#245B8E] shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="rounded-md px-3 py-1.5 text-xs font-semibold transition">{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex gap-4 text-xs font-semibold text-slate-600"><span class="flex items-center gap-2"><i class="size-3 rounded-sm bg-orange-400"></i>Late submissions</span><span class="flex items-center gap-2"><i class="size-3 rounded-sm bg-emerald-500"></i>Excused students</span></div>
        </div>
    </div>
    <div class="mt-7 flex h-64 gap-3 border-b border-slate-200 sm:gap-6">
        <template x-for="bucket in buckets" :key="period + bucket.label">
            <div class="flex min-w-0 flex-1 flex-col justify-end">
                <div class="flex h-52 items-end justify-center gap-1 sm:gap-2">
                    <div class="relative w-5 rounded-t-md bg-orange-400 transition-all hover:bg-orange-500 sm:w-8" :style="`height: ${height(bucket.late)}%`"><span class="absolute -top-6 left-1/2 -translate-x-1/2 text-xs font-bold text-orange-700" x-text="bucket.late"></span></div>
                    <div class="relative w-5 rounded-t-md bg-emerald-500 transition-all hover:bg-emerald-600 sm:w-8" :style="`height: ${height(bucket.excused)}%`"><span class="absolute -top-6 left-1/2 -translate-x-1/2 text-xs font-bold text-emerald-700" x-text="bucket.excused"></span></div>
                </div>
                <p class="py-3 text-center text-xs font-semibold text-slate-500" x-text="bucket.label"></p>
            </div>
        </template>
    </div>
</section>
<div class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b p-5"><div><h2 class="font-bold text-slate-900">Recent Requests</h2><p class="text-sm text-slate-500">Latest excuse slip activity</p></div><a href="{{ route('requests.index') }}" class="text-sm font-semibold text-[#245B8E]">View all →</a></div><div class="overflow-x-auto">@include('requests._table',['items'=>$requests])</div></div>
@endsection
