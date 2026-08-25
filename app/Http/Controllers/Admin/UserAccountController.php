<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdministratorAccountCreated;
use App\Models\User;
use App\Models\Faculty;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
            ->when($request->filled('role'), fn ($query) => $query->where(fn ($roles) => $roles
                ->whereJsonContains('roles', $request->role)
                ->orWhere(fn ($legacy) => $legacy->whereNull('roles')->where('role', $request->role))))
            ->when($request->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.accounts.index', [
            'accounts' => $accounts,
            'roles' => collect(['admin', 'program_head', 'faculty', 'adviser', 'student']),
        ]);
    }

    public function edit(User $account)
    {
        return view('admin.accounts.edit', [
            'account' => $account,
            'unlinkedFaculty' => Faculty::whereNull('user_id')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.accounts.create');
    }

    public function store(Request $request)
    {
        if (! $request->has('roles') && $request->filled('role')) {
            $request->merge(['roles' => [$request->input('role')]]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::in(['admin', 'program_head', 'faculty', 'adviser'])],
            'adviser_year_level' => [Rule::requiredIf(fn () => in_array('adviser', $request->input('roles', []), true)), 'nullable', 'integer', 'between:1,5'],
        ]);
        $roles = array_values($data['roles']);
        $data['role'] = $roles[0];
        $data['roles'] = $roles;
        $data['adviser_year_level'] = in_array('adviser', $roles, true) ? (int) $data['adviser_year_level'] : null;

        $password = Str::random(12);

        DB::transaction(function () use ($data, $password) {
            $administrator = User::create($data + [
                'password' => $password,
                'is_active' => true,
            ]);

            if ($administrator->hasRole('faculty')) {
                Faculty::create(['user_id' => $administrator->id, 'name' => $administrator->name, 'designation' => 'Course Facilitator']);
            }

            Mail::to($administrator->email)->send(
                new AdministratorAccountCreated($administrator, $password)
            );
        });

        return redirect()->route('admin.accounts.index')->with('success', 'Account created and login details emailed.');
    }

    public function update(Request $request, User $account)
    {
        if (! $request->has('roles') && $request->filled('role')) {
            $request->merge(['roles' => [$request->input('role')]]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($account)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::in(['admin', 'program_head', 'faculty', 'adviser', 'student'])],
            'adviser_year_level' => [Rule::requiredIf(fn () => in_array('adviser', $request->input('roles', []), true)), 'nullable', 'integer', 'between:1,5'],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'faculty_id' => ['nullable', Rule::exists('faculty', 'id')->whereNull('user_id')],
        ]);

        $roles = array_values($data['roles']);
        if (in_array('student', $roles, true) && count($roles) > 1) {
            return back()->withErrors(['roles' => 'Student access cannot be combined with another role.'])->withInput();
        }
        $data['role'] = $roles[0];
        $data['roles'] = $roles;
        $data['adviser_year_level'] = in_array('adviser', $roles, true) ? (int) $data['adviser_year_level'] : null;

        if ($account->is($request->user()) && (! array_intersect($roles, ['admin', 'program_head']) || ! (bool) $data['is_active'])) {
            return back()->withErrors(['is_active' => 'You cannot remove your own administrative access or disable your current account.'])->withInput();
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        DB::transaction(function () use ($account, $data, $roles) {
            $account->update($data);
            if (in_array('faculty', $roles, true)) {
                $faculty = $account->faculty;
                if (! $faculty && filled($data['faculty_id'] ?? null)) {
                    $faculty = Faculty::whereNull('user_id')->lockForUpdate()->findOrFail($data['faculty_id']);
                }
                $faculty ??= new Faculty;
                $faculty->fill(['name' => $account->name, 'designation' => $faculty->designation ?: 'Course Facilitator']);
                $faculty->user()->associate($account);
                $faculty->save();
            } elseif ($account->faculty) {
                $account->faculty->update(['name' => $account->name]);
            }
        });

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
