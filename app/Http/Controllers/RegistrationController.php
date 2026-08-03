<?php
namespace App\Http\Controllers;

use App\Http\Requests\RegisterStudentRequest;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\Course;
use App\Exceptions\VirusScanException;
use App\Services\VirusScanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrationController extends Controller
{
    public function create()
    {
        $sections = Section::with('course')->where('is_active', true)
            ->orderBy('year_level')->orderBy('name')->get();

        return view('auth.register', compact('sections'));
    }

    public function store(RegisterStudentRequest $request, VirusScanner $virusScanner)
    {
        $assessmentForm = $request->file('assessment_form');

        try {
            $virusScanner->scan($assessmentForm->getRealPath());
        } catch (VirusScanException $exception) {
            Log::warning('Assessment form antivirus scan rejected an upload.', [
                'infected' => $exception->infected,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'assessment_form' => $exception->infected
                    ? 'The assessment form was rejected because malware was detected.'
                    : 'The assessment form could not be safely scanned. Please try again later.',
            ]);
        }

        $assessmentPath = $this->storeSanitizedAssessmentForm($assessmentForm);

        try {
            $user = DB::transaction(function () use ($request, $assessmentForm, $assessmentPath) {
                $course = Course::where('code', 'BSIT')->firstOrFail();
                $section = Section::firstOrCreate([
                    'course_id' => $course->id,
                    'year_level' => $request->integer('year_level'),
                    'name' => $request->input('block'),
                ], ['is_active' => true]);
                $name = collect([
                    $request->input('first_name'),
                    $request->input('middle_name'),
                    $request->input('last_name'),
                ])->filter(fn ($part) => filled($part))
                    ->map(fn ($part) => trim($part))
                    ->implode(' ');

                $user = User::create([
                    'name' => $name,
                    'email' => strtolower(trim($request->input('email'))),
                    'password' => $request->input('password'),
                    'role' => 'student',
                    'is_active' => true,
                    'registration_verified_at' => null,
                ]);
                Student::create([
                    'user_id' => $user->id,
                    'student_number' => trim($request->input('student_number')),
                    'address' => trim($request->input('address')),
                    'assessment_form_path' => $assessmentPath,
                    'assessment_form_name' => $assessmentForm->getClientOriginalName(),
                    'course_id' => $section->course_id,
                    'section_id' => $section->id,
                    'year_level' => $section->year_level,
                ]);

                return $user;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($assessmentPath);
            throw $exception;
        }

        return redirect()->route('login')->with('success', 'Registration submitted. Please wait for an administrator to verify your account before signing in.');
    }

    private function storeSanitizedAssessmentForm($file): string
    {
        $source = file_get_contents($file->getRealPath());
        $image = $source === false ? false : @imagecreatefromstring($source);

        if ($image === false) {
            throw ValidationException::withMessages([
                'assessment_form' => 'The assessment form could not be processed as a safe image.',
            ]);
        }

        $isPng = exif_imagetype($file->getRealPath()) === IMAGETYPE_PNG;
        ob_start();
        $encoded = $isPng ? imagepng($image, null, 8) : imagejpeg($image, null, 90);
        $sanitized = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || $sanitized === false) {
            throw ValidationException::withMessages([
                'assessment_form' => 'The assessment form could not be processed as a safe image.',
            ]);
        }

        $path = 'student-assessment-forms/'.Str::uuid().($isPng ? '.png' : '.jpg');

        if (! Storage::disk('local')->put($path, $sanitized)) {
            throw ValidationException::withMessages([
                'assessment_form' => 'The assessment form could not be stored. Please try again.',
            ]);
        }

        return $path;
    }
}
