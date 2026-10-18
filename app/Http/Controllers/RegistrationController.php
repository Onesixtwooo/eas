<?php
namespace App\Http\Controllers;

use App\Http\Requests\RegisterStudentRequest;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\Course;
use App\Models\Subject;
use App\Mail\StudentRegistrationOtp;
use App\Services\SanitizedImageStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrationController extends Controller
{
    public function create()
    {
        $sections = Section::with('course')->where('is_active', true)
            ->orderBy('year_level')->orderBy('name')->get();

        $subjects = Subject::where('is_active', true)
            ->whereHas('course', fn ($query) => $query->where('code', 'BSIT'))
            ->orderBy('year_level')->orderBy('code')->get()->groupBy('year_level');

        return view('auth.register', compact('sections', 'subjects'));
    }

    public function store(RegisterStudentRequest $request, SanitizedImageStorage $images)
    {
        $assessmentForm = $request->file('assessment_form');
        $assessmentPath = $images->store($assessmentForm, 'student-assessment-forms', 'assessment_form');

        try {
            $user = DB::transaction(function () use ($request, $assessmentForm, $assessmentPath) {
                $course = Course::where('code', 'BSIT')->firstOrFail();
                $section = Section::firstOrCreate([
                    'course_id' => $course->id,
                    'year_level' => $request->integer('year_level'),
                    'name' => $request->input('block'),
                ], ['is_active' => true]);
                if (! $section->is_active) {
                    throw ValidationException::withMessages([
                        'block' => 'This section is no longer available. Choose an active section.',
                    ]);
                }
                $name = collect([
                    $request->input('first_name'),
                    $request->input('middle_name'),
                    $request->input('last_name'),
                ])->filter(fn ($part) => filled($part))
                    ->map(fn ($part) => trim($part))
                    ->implode(' ');

                $user = (new User([
                    'name' => $name,
                    'email' => strtolower(trim($request->input('email'))),
                    'password' => $request->input('password'),
                ]))->forceFill([
                    'role' => 'student',
                    'is_active' => true,
                    'registration_verified_at' => null,
                    'email_verified_at' => now(),
                ]);
                $user->save();
                $student = Student::create([
                    'user_id' => $user->id,
                    'student_number' => trim($request->input('student_number')),
                    'student_type' => $request->input('student_type', 'regular'),
                    'address' => trim($request->input('address')),
                    'assessment_form_path' => $assessmentPath,
                    'assessment_form_name' => $assessmentForm->getClientOriginalName(),
                    'course_id' => $section->course_id,
                    'section_id' => $section->id,
                    'year_level' => $section->year_level,
                ]);

                if ($student->student_type === 'irregular') {
                    $student->subjects()->sync($request->input('subject_ids', []));
                }

                return $user;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($assessmentPath);
            throw $exception;
        }

        $request->session()->forget([
            'registration_email_otp_hash',
            'registration_email_otp_email',
            'registration_email_otp_expires_at',
        ]);

        return redirect()->route('login')->with('success', 'Email verified and registration submitted. Please wait for an administrator to approve your account.');
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);
        $email = strtolower(trim($data['email']));
        $otp = (string) random_int(100000, 999999);

        Mail::to($email)->send(new StudentRegistrationOtp($otp));

        $request->session()->put([
            'registration_email_otp_hash' => hash('sha256', $otp),
            'registration_email_otp_email' => $email,
            'registration_email_otp_expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return response()->json(['message' => 'A six-digit OTP was sent to your email.']);
    }

}
