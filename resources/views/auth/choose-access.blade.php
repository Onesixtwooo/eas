@extends('layouts.app')
@section('title', 'Choose Access Level')
@section('content')
<div class="mx-auto max-w-2xl py-8">
    <div class="text-center"><p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Dual-role account</p><h1 class="mt-2 text-3xl font-bold text-slate-900">Choose your access level</h1><p class="mt-2 text-slate-500">Select how you want to use the portal. You can switch again from the header.</p></div>
    <form method="post" action="{{ route('access-mode.store') }}" class="mt-7 grid gap-4 sm:grid-cols-2">@csrf
        @foreach($roles as $role)
            <button name="role" value="{{ $role }}" class="rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-sm hover:border-[#245B8E] hover:bg-blue-50"><span class="text-lg font-bold text-[#123A63]">{{ $role === 'faculty' ? 'Instructor level' : ucwords(str_replace('_', ' ', $role)).' level' }}</span><span class="mt-2 block text-sm text-slate-500">{{ $role === 'faculty' ? 'View and handle student requests assigned to you.' : 'Use the portal with '.str_replace('_', ' ', $role).' permissions.' }}</span></button>
        @endforeach
    </form>
    <form method="post" action="{{ route('logout') }}" class="mt-6 text-center">@csrf<button class="min-w-48 rounded-xl border border-red-300 bg-white px-8 py-3 font-semibold text-red-700 hover:bg-red-50">Logout</button></form>
</div>
@endsection
