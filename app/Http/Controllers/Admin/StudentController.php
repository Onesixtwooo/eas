<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterStudentRequest;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            $course = Course::where('code', 'BSIT')->firstOrFail();
            $section = Section::firstOrCreate([
                'course_id' => $course->id,
                'year_level' => $request->integer('year_level'),
                'name' => $request->input('block'),
            ], ['is_active' => true]);
            $name = collect([$request->first_name, $request->middle_name, $request->last_name])
                ->filter()->map(fn ($part) => trim($part))->implode(' ');
            $user = User::create([
                'name' => $name,
                'email' => strtolower(trim($request->email)),
                'password' => $request->password,
                'role' => 'student',
                'is_active' => true,
                'registration_verified_at' => now(),
            ]);
            Student::create([
                'user_id' => $user->id,
                'student_number' => trim($request->student_number),
                'address' => trim($request->address),
                'course_id' => $section->course_id,
                'section_id' => $section->id,
                'year_level' => $section->year_level,
            ]);
        });

        return redirect()->route('admin.students.index')->with('success', 'Student account created successfully.');
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
            ->when($request->status === 'pending', fn ($query) => $query->whereHas('user', fn ($user) => $user->whereNull('registration_verified_at')))
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
            'pending' => Student::whereHas('user', fn ($query) => $query->whereNull('registration_verified_at'))->count(),
        ];

        return view('admin.students.index', compact('students', 'sections', 'summary'));
    }

    public function show(Student $student)
    {
        $student->load(['user', 'course', 'section', 'subjects']);
        $student->loadCount('requests');
        $sections = Section::where('course_id', $student->course_id)
            ->where('is_active', true)
            ->orderBy('year_level')
            ->orderBy('name')
            ->get();

        return view('admin.students.show', compact('student', 'sections'));
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
        $student->user->update(['is_active' => ! $student->user->is_active]);

        return back()->with(
            'success',
            $student->user->is_active ? 'Student account activated.' : 'Student account deactivated.'
        );
    }

    public function verify(Student $student)
    {
        if (! $student->user->registration_verified_at) {
            $student->user->update(['registration_verified_at' => now()]);
        }

        return back()->with('success', 'Student registration verified. The student can now sign in.');
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

    public function destroy(Student $student)
    {
        $this->deleteStudents(collect([$student]));

        return redirect()->route('admin.students.index')
            ->with('success', 'Student and linked account deleted permanently.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ], [
            'student_ids.required' => 'Select at least one student to delete.',
        ]);

        $students = Student::with('requests.documents')
            ->whereIn('id', $data['student_ids'])
            ->get();

        $count = $students->count();
        $this->deleteStudents($students);

        return redirect()->route('admin.students.index')
            ->with('success', "{$count} student account(s) deleted permanently.");
    }

    private function deleteStudents($students): void
    {
        DB::transaction(function () use ($students) {
            foreach ($students as $student) {
                $student->loadMissing('requests.documents');

                if ($student->assessment_form_path) {
                    Storage::disk('local')->delete($student->assessment_form_path);
                }

                foreach ($student->requests as $excuseRequest) {
                    foreach ($excuseRequest->documents as $document) {
                        Storage::disk($document->disk)->delete($document->path);
                    }
                }

                $student->user()->delete();
            }
        });
    }
}
