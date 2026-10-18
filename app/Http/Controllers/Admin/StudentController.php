<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterStudentRequest;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\Course;
use App\Mail\StudentRegistrationDeclined;
use App\Mail\StudentEmailVerificationCode;
use App\Services\AccountArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class StudentController extends Controller
{
    public function create()
    {
        $sections = Section::with('course')->where('is_active', true)
            ->orderBy('year_level')->orderBy('name')->get();

        return view('admin.students.create', compact('sections'));
    }

    public function store(RegisterStudentRequest $request)
    {
        DB::transaction(function () use ($request) {
            $otp = (string) random_int(100000, 999999);
            $course = Course::where('code', 'BSIT')->firstOrFail();
            $section = Section::firstOrCreate([
                'course_id' => $course->id,
                'year_level' => $request->integer('year_level'),
                'name' => $request->input('block'),
            ], ['is_active' => true]);
            if (! $section->is_active) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'block' => 'This section is no longer available. Choose an active section.',
                ]);
            }
            $name = collect([$request->first_name, $request->middle_name, $request->last_name])
                ->filter()->map(fn ($part) => trim($part))->implode(' ');
            $user = (new User([
                'name' => $name,
                'email' => strtolower(trim($request->email)),
                'password' => $request->password,
            ]))->forceFill([
                'role' => 'student',
                'is_active' => true,
                'registration_verified_at' => now(),
                'email_verification_otp' => Hash::make($otp),
                'email_verification_otp_expires_at' => now()->addMinutes(10),
            ]);
            $user->save();
            Student::create([
                'user_id' => $user->id,
                'student_number' => trim($request->student_number),
                'address' => trim($request->address),
                'course_id' => $section->course_id,
                'section_id' => $section->id,
                'year_level' => $section->year_level,
            ]);
            Mail::to($user->email)->send(new StudentEmailVerificationCode($user, $otp));
        });

        return redirect()->route('admin.students.index')->with('success', 'Student account created. An email verification code was sent to the student.');
    }

    public function index(Request $request)
    {
        $students = Student::query()
            ->with(['user', 'course', 'section'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->input('search'));
                $query->where(function ($query) use ($search) {
                    $query->where('student_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('section_id'), fn ($query) => $query->where('section_id', $request->integer('section_id')))
            ->when($request->status === 'active', fn ($query) => $query->whereHas('user', fn ($user) => $user->where('is_active', true)))
            ->when($request->status === 'inactive', fn ($query) => $query->whereHas('user', fn ($user) => $user->where('is_active', false)))
            ->when($request->status === 'pending', fn ($query) => $query->whereHas('user', fn ($user) => $user->whereNull('registration_verified_at')->whereNull('registration_declined_at')))
            ->when($request->status === 'declined', fn ($query) => $query->whereHas('user', fn ($user) => $user->whereNotNull('registration_declined_at')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $sections = Section::with('course')
            ->whereIn('id', Student::query()->select('section_id'))
            ->orderBy('year_level')
            ->orderBy('name')
            ->get();

        $summary = [
            'total' => Student::count(),
            'active' => Student::whereHas('user', fn ($query) => $query->where('is_active', true))->count(),
            'inactive' => Student::whereHas('user', fn ($query) => $query->where('is_active', false))->count(),
            'pending' => Student::whereHas('user', fn ($query) => $query->whereNull('registration_verified_at')->whereNull('registration_declined_at'))->count(),
        ];

        return view('admin.students.index', compact('students', 'sections', 'summary'));
    }

    public function show(Student $student)
    {
        $student->load(['user', 'course', 'section', 'subjects']);
        $student->loadCount('requests');
        $requests = $student->requests()->with(['subject', 'subjects'])
            ->latest()->orderByDesc('id')->paginate(15, ['*'], 'request_page');
        $sections = Section::where('course_id', $student->course_id)
            ->where('is_active', true)
            ->orderBy('year_level')
            ->orderBy('name')
            ->get();

        return view('admin.students.show', compact('student', 'sections', 'requests'));
    }

    public function updateAcademicPlacement(Request $request, Student $student)
    {
        $data = $request->validate([
            'year_level' => ['required', 'integer', 'between:1,5'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ]);

        $section = Section::whereKey($data['section_id'])
            ->where('course_id', $student->course_id)
            ->where('year_level', $data['year_level'])
            ->where('is_active', true)
            ->first();

        if (! $section) {
            return back()->withErrors([
                'section_id' => 'Select an active section for the chosen year level and the student’s current course.',
            ])->withInput();
        }

        $student->update([
            'year_level' => $section->year_level,
            'section_id' => $section->id,
        ]);

        return back()->with('success', 'Student year level and section updated successfully.');
    }

    public function toggleStatus(Student $student)
    {
        if ($student->user->registration_declined_at) {
            return back()->with('error', 'A declined registration cannot be activated. Archive the account.');
        }

        $student->user->forceFill(['is_active' => ! $student->user->is_active])->save();

        return back()->with(
            'success',
            $student->user->is_active ? 'Student account activated.' : 'Student account deactivated.'
        );
    }

    public function verify(Student $student)
    {
        User::whereKey($student->user_id)
            ->whereNull('registration_verified_at')
            ->whereNull('registration_declined_at')
            ->update(['registration_verified_at' => now()]);
        $student->user->refresh();

        if ($student->user->registration_declined_at) {
            return back()->with('error', 'A declined registration cannot be verified. Archive the account if it still exists.');
        }

        $message = ! $student->user->is_active
            ? 'Student registration verified. The account remains inactive until activated.'
            : ($student->user->email_verified_at
                ? 'Student registration verified. The student can now sign in.'
                : 'Student registration verified. The student can sign in after verifying their email address.');

        return back()->with('success', $message);
    }

    public function decline(Request $request, Student $student, AccountArchiveService $archives)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'confirm_delete' => ['required', 'accepted'],
        ]);

        $updated = User::whereKey($student->user_id)
            ->whereNull('registration_verified_at')
            ->whereNull('registration_declined_at')
            ->update([
                'registration_declined_at' => now(),
                'registration_decline_reason' => trim($data['reason']),
                'is_active' => false,
            ]);
        if (! $updated) {
            return back()->with('error', 'Only pending registrations can be declined.');
        }
        $student->user->refresh();

        $recipient = $student->user->email;
        $notice = new StudentRegistrationDeclined($student->user);
        $archive = $archives->archiveAndRemove($student->user, $request->user());

        try {
            Mail::to($recipient)->send($notice);
        } catch (Throwable $error) {
            Log::error('Declined registration notification could not be sent.', [
                'archive_id' => $archive->archive_id,
                'error' => $error->getMessage(),
            ]);

            return redirect()->route('admin.students.index')->with('error',
                'Registration declined and archived, but the notification email could not be sent.');
        }

        return redirect()->route('admin.students.index')->with('success',
            $archive->outcome === 'deleted'
                ? 'Registration declined. The account was deleted, its records archived, and the student notified.'
                : 'Registration declined. The account was disabled, its records archived, and the student notified.');
    }

    public function assessmentForm(Student $student)
    {
        abort_unless($student->assessment_form_path && Storage::disk('local')->exists($student->assessment_form_path), 404);

        return Storage::disk('local')->response(
            $student->assessment_form_path,
            $student->assessment_form_name ?? 'assessment-form',
            [
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function destroy(Student $student, AccountArchiveService $archives)
    {
        $archive = $archives->archiveAndRemove($student->user, auth()->user());

        return redirect()->route('admin.students.index')
            ->with('success', $archive->outcome === 'deleted'
                ? 'Student account deleted and its records archived.'
                : 'Student account archived and disabled; linked records remain active.');
    }

    public function bulkDestroy(Request $request, AccountArchiveService $archives)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ], [
            'student_ids.required' => 'Select at least one student to delete.',
        ]);

        $students = Student::with('user')
            ->whereIn('id', $data['student_ids'])
            ->get();

        $count = $students->count();
        $outcomes = $archives->archiveManyAndRemove($students->pluck('user'), $request->user())->pluck('outcome');

        return redirect()->route('admin.students.index')
            ->with('success', "{$count} student account(s) archived: {$outcomes->filter(fn ($outcome) => $outcome === 'deleted')->count()} deleted, {$outcomes->filter(fn ($outcome) => $outcome === 'deactivated')->count()} disabled.");
    }

    public function bulkDeactivate(Request $request)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ], [
            'student_ids.required' => 'Select at least one student to deactivate.',
        ]);

        $userIds = Student::whereIn('id', $data['student_ids'])->select('user_id');
        $count = User::whereIn('id', $userIds)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return redirect()->route('admin.students.index')
            ->with('success', $count === 0
                ? 'Selected student accounts are already inactive.'
                : "{$count} student account(s) deactivated.");
    }

}
