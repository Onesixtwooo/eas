<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = User::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(fn ($user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->role))
            ->when($request->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.accounts.index', [
            'accounts' => $accounts,
            'roles' => User::query()->distinct()->orderBy('role')->pluck('role'),
        ]);
    }

    public function edit(User $account)
    {
        return view('admin.accounts.edit', compact('account'));
    }

    public function create()
    {
        return view('admin.accounts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create($data + ['role' => 'admin', 'is_active' => true]);

        return redirect()->route('admin.accounts.index')->with('success', 'Administrator account created.');
    }

    public function update(Request $request, User $account)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($account)],
            'role' => ['required', Rule::in(['admin', 'program_head', 'faculty', 'student'])],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($account->is($request->user()) && ($data['role'] !== 'admin' || ! (bool) $data['is_active'])) {
            return back()->withErrors(['is_active' => 'You cannot remove your own administrator access or disable your current account.'])->withInput();
        }

        if (blank($data['password'])) {
            unset($data['password']);
        }

        $account->update($data);

        return redirect()->route('admin.accounts.index')->with('success', 'User account updated.');
    }

    public function destroy(Request $request, User $account)
    {
        if ($account->is($request->user())) {
            return back()->with('error', 'You cannot delete the account you are currently using.');
        }

        try {
            $account->delete();
        } catch (QueryException) {
            return back()->with('error', 'This account is linked to records that must be retained. Disable it instead.');
        }

        return redirect()->route('admin.accounts.index')->with('success', 'User account deleted.');
    }
}
