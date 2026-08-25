<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'OLSHCO EAS')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>[x-cloak] { display: none !important; }</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body x-data="{ menu: false, menuTouchX: null }" @keydown.escape.window="menu = false" :class="{ 'overflow-hidden': menu }" class="min-h-screen">
    @php($isAccessModePage = request()->routeIs('access-mode.create'))
    <div id="realtime-update" class="no-print fixed bottom-5 right-5 z-50 hidden max-w-sm rounded-2xl border border-blue-200 bg-white p-4 shadow-2xl">
        <p class="font-semibold text-slate-900">New system updates are available.</p>
        <p class="mt-1 text-sm text-slate-500">Refresh when ready. Your unsaved form entries will not be discarded automatically.</p>
        <button type="button" onclick="location.reload()" class="mt-3 rounded-lg bg-[#123A63] px-4 py-2 text-sm font-semibold text-white">Refresh page</button>
    </div>
    @unless($isAccessModePage)
    <button type="button" x-cloak x-show="menu" x-transition.opacity @click="menu = false" class="no-print fixed inset-0 z-[35] bg-slate-950/50 lg:hidden" aria-label="Close menu"></button>
    <aside id="mobile-navigation" :class="menu ? 'translate-x-0' : '-translate-x-full'" @touchstart.passive="menuTouchX = $event.touches[0].clientX" @touchend="if (menuTouchX !== null && $event.changedTouches[0].clientX < menuTouchX - 50) menu = false; menuTouchX = null" class="no-print fixed inset-y-0 left-0 z-40 flex w-72 max-w-[85vw] flex-col bg-[#123A63] text-white shadow-2xl transition-transform duration-200 lg:translate-x-0 lg:shadow-none">
        <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
            <button type="button" @click="menu = false" class="shrink-0 rounded-full lg:pointer-events-none" aria-label="Close menu">
                <img src="{{ asset('images.jpg') }}" alt="OLSHCO logo" class="size-11 rounded-full bg-white object-cover">
            </button>
            <div class="min-w-0 flex-1"><b class="block">OLSHCO EAS</b><span class="text-xs text-blue-200">Academic Services Portal</span></div>
        </div>
        <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto p-4">
            @if(auth()->user()->isDualRole())
                <form method="post" action="{{ route('access-mode.store') }}" class="mb-3 sm:hidden">@csrf<label for="mobile-access-mode" class="px-4 text-[11px] font-bold uppercase tracking-widest text-blue-300">Access level</label><select id="mobile-access-mode" name="role" onchange="this.form.submit()" class="mt-2 border-white/20 bg-white text-[#123A63]">@foreach(auth()->user()->assignedRoles() as $role)<option value="{{ $role }}" @selected(auth()->user()->role === $role)>{{ $role === 'faculty' ? 'Instructor level' : ucwords(str_replace('_', ' ', $role)).' level' }}</option>@endforeach</select></form>
            @endif
            @php($links = auth()->user()->role === 'student'
                ? [['requests.index', 'My Requests', '=']]
                : (auth()->user()->role === 'adviser'
                    ? [['advisory.index', 'My Advisory', '@'], ['advisory.absences', 'Daily Absences', '=']]
                    : [['dashboard', 'Dashboard', '*'], ['requests.index', 'Excuse Requests', '=']]))
            @if(auth()->user()->role === 'student') @php($links[] = ['requests.create', 'Submit Excuse Slip', '+']) @endif
            @if(auth()->user()->role === 'faculty') @php($links[] = ['student-reports.index', 'Student Report', '@']) @endif
            @if(in_array(auth()->user()->role, ['student', 'admin', 'program_head'])) @php($links[] = ['messages.index', 'Messages', '#']) @endif
            @foreach($links as [$route, $label, $icon])
                <a href="{{ route($route) }}" @click="menu = false" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium {{ request()->routeIs($route) || ($route === 'messages.index' && request()->routeIs('messages.*')) || ($route === 'admin.subjects.index' && request()->routeIs('admin.subjects.*')) ? 'bg-white text-[#123A63] shadow' : 'text-blue-100 hover:bg-white/10' }}">
                    <span class="w-5 text-center text-lg">{{ $icon }}</span>{{ $label }}
                </a>
            @endforeach

            @if(in_array(auth()->user()->role, ['admin', 'program_head']))
                <p class="px-4 pb-1 pt-5 text-[11px] font-bold uppercase tracking-[.18em] text-blue-300">Administration</p>
                @foreach([
                    ['admin.students.index', 'Students', '@'],
                    ['admin.faculty.index', 'Faculty', '@'],
                    ['admin.subjects.index', 'Subjects', '#'],
                    ['admin.instructor-assignments.index', 'Instructor Assignments', '+'],
                ] as [$route, $label, $icon])
                    <a href="{{ route($route) }}" @click="menu = false" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium {{ request()->routeIs($route) || ($route === 'admin.subjects.index' && request()->routeIs('admin.subjects.*')) ? 'bg-white text-[#123A63] shadow' : 'text-blue-100 hover:bg-white/10' }}">
                        <span class="w-5 text-center text-lg">{{ $icon }}</span>{{ $label }}
                    </a>
                @endforeach
            @endif

            <p class="px-4 pb-1 pt-5 text-[11px] font-bold uppercase tracking-[.18em] text-blue-300">Account</p>
            @php($accountLinks = [])
            @if(in_array(auth()->user()->role, ['admin', 'program_head'])) @php($accountLinks[] = ['admin.accounts.index', 'User Accounts', '@']) @endif
            @php($accountLinks[] = ['profile', 'Profile', 'O'])
            @foreach($accountLinks as [$route, $label, $icon])
                <a href="{{ route($route) }}" @click="menu = false" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium {{ request()->routeIs($route) || ($route === 'admin.accounts.index' && request()->routeIs('admin.accounts.*')) ? 'bg-white text-[#123A63] shadow' : 'text-blue-100 hover:bg-white/10' }}">
                    <span class="w-5 text-center text-lg">{{ $icon }}</span>{{ $label }}
                </a>
            @endforeach
        </nav>
        <form method="post" action="{{ route('logout') }}" class="shrink-0 border-t border-white/10 p-4">
            @csrf
            <button class="flex w-full items-center justify-center rounded-xl border border-white/20 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/40">Sign out</button>
        </form>
    </aside>
    @endunless
    <div class="{{ $isAccessModePage ? '' : 'lg:pl-72' }}">
        <header class="no-print sticky top-0 z-30 flex h-20 items-center gap-3 border-b bg-white/95 px-4 backdrop-blur sm:gap-4 sm:px-8">
            @unless($isAccessModePage)
            <button type="button" @click="menu = true" :aria-expanded="menu.toString()" aria-controls="mobile-navigation" class="grid size-10 shrink-0 place-items-center rounded-xl border border-slate-300 bg-white text-[#123A63] shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100 lg:hidden" aria-label="Open navigation menu">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>
            @endunless
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-semibold uppercase tracking-widest text-[#245B8E]">{{ str_replace('_', ' ', auth()->user()->role) }}</p>
                <p class="truncate font-semibold text-slate-800">{{ auth()->user()->name }}</p>
            </div>
            @if(auth()->user()->isDualRole() && ! $isAccessModePage)
                <form method="post" action="{{ route('access-mode.store') }}" class="hidden sm:block">
                    @csrf
                    <label for="header-access-mode" class="sr-only">Access level</label>
                    <select id="header-access-mode" name="role" onchange="this.form.submit()" class="min-w-44 rounded-xl border border-slate-300 bg-white py-2 text-sm font-semibold text-[#123A63]">
                        @foreach(auth()->user()->assignedRoles() as $role)<option value="{{ $role }}" @selected(auth()->user()->role === $role)>{{ $role === 'faculty' ? 'Instructor level' : ucwords(str_replace('_', ' ', $role)).' level' }}</option>@endforeach
                    </select>
                </form>
            @endif
            <div class="grid size-10 shrink-0 place-items-center rounded-full bg-[#123A63] font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
        </header>
        <main class="p-4 sm:p-8">
            @if(session('success'))<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>@endif
            @yield('content')
        </main>
    </div>
    <script>
        window.addEventListener('pageshow', event => {
            if (event.persisted) location.reload();
        });

        (() => {
            let revision = @json(cache(\App\Http\Middleware\TrackSystemChanges::CACHE_KEY, 'initial'));
            let formIsDirty = false;
            const updateNotice = document.getElementById('realtime-update');

            document.addEventListener('input', event => {
                if (event.target.closest('form')) formIsDirty = true;
            });
            document.addEventListener('change', event => {
                if (event.target.closest('form')) formIsDirty = true;
            });

            async function checkForUpdates() {
                if (document.hidden) return;

                try {
                    const response = await fetch(@json(route('realtime.version')), {
                        headers: {'Accept': 'application/json'},
                        cache: 'no-store',
                    });
                    if (!response.ok) return;

                    const current = (await response.json()).revision;
                    if (current === revision) return;
                    revision = current;

                    if (formIsDirty) {
                        updateNotice.classList.remove('hidden');
                    } else {
                        location.reload();
                    }
                } catch (error) {
                    // A temporary connection failure is retried on the next interval.
                }
            }

            setInterval(checkForUpdates, 5000);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) checkForUpdates();
            });
        })();
    </script>
</body>
</html>
