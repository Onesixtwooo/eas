<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'OLSHCO EAS')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>[x-cloak] { display: none !important; }</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body x-data="{ menu: false }" class="min-h-screen">
    <aside :class="menu ? 'translate-x-0' : '-translate-x-full'" class="no-print fixed inset-y-0 left-0 z-40 w-72 bg-[#123A63] text-white transition lg:translate-x-0">
        <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
            <img src="{{ asset('images.jpg') }}" alt="OLSHCO logo" class="size-11 rounded-full bg-white object-cover">
            <div><b class="block">OLSHCO EAS</b><span class="text-xs text-blue-200">Academic Services Portal</span></div>
        </div>
        <nav class="h-[calc(100vh-5rem)] space-y-1 overflow-y-auto p-4 pb-24">
            @php($links = auth()->user()->role === 'student'
                ? [['requests.index', 'My Requests', '=']]
                : [['dashboard', 'Dashboard', '*'], ['requests.index', 'Excuse Requests', '=']])
            @if(auth()->user()->role === 'student') @php($links[] = ['requests.create', 'Submit Excuse Slip', '+']) @endif
            @if(in_array(auth()->user()->role, ['admin', 'program_head'])) @php($links[] = ['reports', 'Reports', '#']) @endif
            @foreach($links as [$route, $label, $icon])
                <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium {{ request()->routeIs($route) || ($route === 'admin.subjects.index' && request()->routeIs('admin.subjects.*')) ? 'bg-white text-[#123A63] shadow' : 'text-blue-100 hover:bg-white/10' }}">
                    <span class="w-5 text-center text-lg">{{ $icon }}</span>{{ $label }}
                </a>
            @endforeach

            @if(auth()->user()->role === 'admin')
                <p class="px-4 pb-1 pt-5 text-[11px] font-bold uppercase tracking-[.18em] text-blue-300">Administration</p>
                @foreach([
                    ['admin.students.index', 'Students', '@'],
                    ['admin.subjects.index', 'Subjects', '#'],
                    ['admin.instructor-assignments.index', 'Instructor Assignments', '+'],
                ] as [$route, $label, $icon])
                    <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium {{ request()->routeIs($route) || ($route === 'admin.subjects.index' && request()->routeIs('admin.subjects.*')) ? 'bg-white text-[#123A63] shadow' : 'text-blue-100 hover:bg-white/10' }}">
                        <span class="w-5 text-center text-lg">{{ $icon }}</span>{{ $label }}
                    </a>
                @endforeach
            @endif

            <p class="px-4 pb-1 pt-5 text-[11px] font-bold uppercase tracking-[.18em] text-blue-300">Account</p>
            @php($accountLinks = [])
            @if(auth()->user()->role === 'admin') @php($accountLinks[] = ['admin.accounts.index', 'User Accounts', '@']) @endif
            @php($accountLinks[] = ['profile', 'Profile', 'O'])
            @foreach($accountLinks as [$route, $label, $icon])
                <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium {{ request()->routeIs($route) || ($route === 'admin.accounts.index' && request()->routeIs('admin.accounts.*')) ? 'bg-white text-[#123A63] shadow' : 'text-blue-100 hover:bg-white/10' }}">
                    <span class="w-5 text-center text-lg">{{ $icon }}</span>{{ $label }}
                </a>
            @endforeach
        </nav>
        <form method="post" action="{{ route('logout') }}" class="absolute inset-x-4 bottom-4">
            @csrf
            <button class="w-full rounded-xl border border-white/20 px-4 py-3 text-left text-sm hover:bg-white/10">Sign out</button>
        </form>
    </aside>
    <div class="lg:pl-72">
        <header class="no-print sticky top-0 z-30 flex h-20 items-center justify-between border-b bg-white/95 px-4 backdrop-blur sm:px-8">
            <button @click="menu = !menu" class="rounded-lg border p-2 lg:hidden">Menu</button>
            <div><p class="text-xs font-semibold uppercase tracking-widest text-[#245B8E]">{{ str_replace('_', ' ', auth()->user()->role) }}</p><p class="font-semibold text-slate-800">{{ auth()->user()->name }}</p></div>
            <div class="grid size-10 place-items-center rounded-full bg-[#123A63] font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
        </header>
        <main class="p-4 sm:p-8">
            @if(session('success'))<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>@endif
            @yield('content')
        </main>
    </div>
</body>
</html>
