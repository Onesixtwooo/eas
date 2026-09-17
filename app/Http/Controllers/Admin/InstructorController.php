<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInstructorRequest;
use App\Mail\AdministratorAccountCreated;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InstructorController extends Controller
{
    public function index(Request $request)
    {
        $faculty = Faculty::query()
            ->with('user')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(fn ($faculty) => $faculty
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$search}%")));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.faculty.index', compact('faculty'));
    }

    public function createAccount()
    {
        return view('admin.faculty.create', [
            'unlinkedFaculty' => Faculty::whereNull('user_id')->orderBy('name')->get(),
        ]);
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'faculty_id' => ['nullable', Rule::exists('faculty', 'id')->whereNull('user_id')],
            'name' => ['required_without:faculty_id', 'nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:255', 'unique:faculty,employee_number'],
            'designation' => ['required_without:faculty_id', 'nullable', 'string', 'max:255'],
        ]);
        $existingUser = User::where('email', $data['email'])->first();
        if ($existingUser?->hasRole('student')) {
            throw ValidationException::withMessages(['email' => 'A student account cannot also be used as a faculty account.']);
        }
        if ($existingUser?->faculty && blank($data['faculty_id'] ?? null)) {
            throw ValidationException::withMessages(['faculty_id' => 'Select the existing instructor profile that this account should use.']);
        }

        $created = DB::transaction(function () use ($data, $existingUser) {
            $faculty = filled($data['faculty_id'] ?? null)
                ? Faculty::whereNull('user_id')->lockForUpdate()->findOrFail($data['faculty_id'])
                : new Faculty([
                    'name' => trim($data['name']),
                    'employee_number' => filled($data['employee_number'] ?? null) ? trim($data['employee_number']) : null,
                    'designation' => trim($data['designation']),
                ]);

            if ($existingUser) {
                $user = User::lockForUpdate()->findOrFail($existingUser->id);
                $currentFaculty = $user->faculty()->lockForUpdate()->first();
                if ($currentFaculty && ! $currentFaculty->is($faculty)) {
                    $hasRecords = InstructorAssignment::where('faculty_id', $currentFaculty->id)->exists()
                        || DB::table('subjects')->where('facilitator_id', $currentFaculty->id)->exists()
                        || DB::table('excuse_requests')->where('facilitator_id', $currentFaculty->id)->exists()
                        || DB::table('excuse_request_subject')->where('facilitator_id', $currentFaculty->id)->exists()
                        || DB::table('instructor_acknowledgments')->where('faculty_id', $currentFaculty->id)->exists();
                    if ($hasRecords) {
                        throw ValidationException::withMessages(['faculty_id' => 'The account is already linked to another faculty profile that contains academic records. Please contact the system administrator to merge them.']);
                    }
                    $currentFaculty->delete();
                }
                $user->forceFill(['roles' => array_values(array_unique([...$user->assignedRoles(), 'faculty']))])->save();
            } else {
                $password = Str::random(12);
                $user = (new User([
                    'name' => $faculty->name,
                    'email' => $data['email'],
                    'password' => $password,
                ]))->forceFill([
                    'role' => 'faculty',
                    'roles' => ['faculty'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
                $user->save();
            }
            $faculty->user()->associate($user);
            $faculty->save();

            if (! $existingUser) {
                Mail::to($user->email)->send(new AdministratorAccountCreated($user, $password));
            }

            return ! $existingUser;
        });

        return redirect()->route('admin.faculty.index')->with(
            'success',
            $created
                ? 'Faculty account created and login details emailed.'
                : 'The existing account is now connected and has Admin/Instructor access.'
        );
    }

    public function edit(Faculty $faculty)
    {
        $faculty->load('user');

        return view('admin.faculty.edit', compact('faculty'));
    }

    public function update(Request $request, Faculty $faculty)
    {
        $faculty->load('user');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:255', Rule::unique('faculty', 'employee_number')->ignore($faculty)],
            'designation' => ['required', 'string', 'max:255'],
            'email' => $faculty->user_id
                ? ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($faculty->user)]
                : ['nullable'],
            'is_active' => $faculty->user_id ? ['required', 'boolean'] : ['nullable'],
            'password' => $faculty->user_id ? ['nullable', 'string', 'min:8', 'confirmed'] : ['nullable'],
        ]);

        if ($faculty->user?->is($request->user()) && ! (bool) $data['is_active']) {
            return back()->withErrors(['is_active' => 'You cannot disable the account you are currently logged into.'])->withInput();
        }

        DB::transaction(function () use ($faculty, $data) {
            $faculty->update([
                'name' => trim($data['name']),
                'employee_number' => filled($data['employee_number'] ?? null) ? trim($data['employee_number']) : null,
                'designation' => trim($data['designation']),
            ]);

            if ($faculty->user_id) {
                $faculty->user->fill([
                    'name' => trim($data['name']),
                    'email' => $data['email'],
                ]);
                if (filled($data['password'] ?? null)) {
                    $faculty->user->password = $data['password'];
                }
                $faculty->user->forceFill([
                    'is_active' => (bool) $data['is_active'],
                ])->save();
            }
        });

        return redirect()->route('admin.faculty.index')->with('success', 'Faculty information updated.');
    }

    public function create()
    {
        return view('admin.instructors.create');
    }

    public function store(StoreInstructorRequest $request)
    {
        $data = $request->validated();

        Faculty::create([
            'name' => trim($data['name']),
            'designation' => trim($data['designation']),
        ]);

        return redirect()->route('admin.instructor-assignments.index')
            ->with('success', 'Instructor added to the assignment list.');
    }
}
