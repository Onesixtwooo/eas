@extends('layouts.app')

@section('title', 'User Accounts')

@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-[#245B8E]">Account</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">User Accounts</h1>
        <p class="mt-2 text-slate-500">View the administrator, program head, faculty, and student accounts registered for portal access.</p>
    </div>
    <a href="{{ route('admin.accounts.create') }}" class="rounded-xl bg-[#123A63] px-5 py-3 text-center font-semibold text-white hover:bg-[#245B8E]">+ Add Admin</a>
</div>

<form method="get" class="mt-7 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_220px_180px_auto]">
    <input name="search" value="{{ request('search') }}" placeholder="Search name or email…">
    <select name="role">
        <option value="">All roles</option>
        @foreach($roles as $role)
            <option value="{{ $role }}" @selected(request('role') === $role)>{{ ucwords(str_replace('_', ' ', $role)) }}</option>
        @endforeach
    </select>
    <select name="status">
        <option value="">All statuses</option>
        <option value="active" @selected(request('status') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
    </select>
    <div class="flex gap-2">
        <button class="rounded-xl bg-[#123A63] px-6 font-semibold text-white">Filter</button>
        @if(request()->hasAny(['search', 'role', 'status']))
            <a href="{{ route('admin.accounts.index') }}" class="grid place-items-center rounded-xl border px-4 text-sm font-semibold">Clear</a>
        @endif
    </div>
</form>

<div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-4">Account</th>
                    <th class="px-5 py-4">Role</th>
                    <th class="px-5 py-4">Access</th>
                    <th class="px-5 py-4">Registered</th>
                    <th class="px-5 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($accounts as $account)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-[#123A63] font-bold text-white">{{ strtoupper(substr($account->name, 0, 1)) }}</span>
                                <div><p class="font-semibold text-slate-900">{{ $account->name }}</p><p class="text-xs text-slate-500">{{ $account->email }}</p></div>
                            </div>
                        </td>
                        <td class="px-5 py-4"><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ ucwords(str_replace('_', ' ', $account->role)) }}</span></td>
                        <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $account->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">{{ $account->is_active ? 'Can log in' : 'Disabled' }}</span></td>
                        <td class="px-5 py-4 text-slate-600">{{ $account->created_at->format('M d, Y') }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.accounts.edit', $account) }}" class="rounded-lg border px-3 py-2 font-semibold text-[#245B8E] hover:bg-blue-50">Edit</a>
                                @if(!$account->is(auth()->user()))
                                    <form method="post" action="{{ route('admin.accounts.destroy', $account) }}" onsubmit="return confirm('Delete the account for {{ addslashes($account->name) }}? Linked profile records may also be removed. This cannot be undone.')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-red-300 px-3 py-2 font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-16 text-center text-slate-500">No user accounts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $accounts->links() }}</div>
@endsection
