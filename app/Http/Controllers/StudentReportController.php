<?php

namespace App\Http\Controllers;

use App\Models\DailyAbsenceReport;
use App\Models\InstructorAssignment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentReportController extends Controller
{
    public function index(Request $request)
    {
        $faculty = $request->user()->faculty;
        abort_unless($faculty, 403, 'This faculty account is not linked to a faculty profile.');

        $assignments = InstructorAssignment::query()
            ->with(['course', 'subject', 'section'])
            ->where('faculty_id', $faculty->id)
            ->where('is_active', true)
            ->orderBy('course_id')
            ->orderBy('year_level')
            ->orderBy('subject_id')
            ->get();

        $assignment = $request->filled('assignment_id')
            ? $assignments->firstWhere('id', $request->integer('assignment_id'))
            : $assignments->first();
        abort_if($request->filled('assignment_id') && ! $assignment, 404);

        $students = $assignment
            ? $this->studentsFor($assignment)->with(['user', 'course', 'section'])->orderBy('student_number')->get()
            : collect();
        $reportedAbsences = $assignment
            ? DailyAbsenceReport::where('instructor_assignment_id', $assignment->id)
                ->whereDate('report_date', today())
                ->get()
                ->keyBy('student_id')
            : collect();
        $reportedStudentIds = $reportedAbsences->keys()->all();
        $reportedComments = $reportedAbsences->map->comment;
        $adviserComments = $reportedAbsences->map->adviser_comment;
        $adviserActions = $reportedAbsences->map->adviser_action;

        return view('faculty.student-reports.index', compact('assignments', 'assignment', 'students', 'reportedStudentIds', 'reportedComments', 'adviserComments', 'adviserActions'));
    }

    public function store(Request $request)
    {
        $faculty = $request->user()->faculty;
        abort_unless($faculty, 403, 'This faculty account is not linked to a faculty profile.');

        $data = $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:instructor_assignments,id'],
            'absent_student_ids' => ['nullable', 'array'],
            'absent_student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
            'comments' => ['nullable', 'array'],
            'comments.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignment = InstructorAssignment::query()
            ->whereKey($data['assignment_id'])
            ->where('faculty_id', $faculty->id)
            ->where('is_active', true)
            ->firstOrFail();
        $eligibleStudentIds = $this->studentsFor($assignment)->pluck('id');
        $absentStudentIds = collect($data['absent_student_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        abort_unless($absentStudentIds->diff($eligibleStudentIds)->isEmpty(), 403, 'One or more students are not assigned to this class.');

        DB::transaction(function () use ($assignment, $absentStudentIds, $data) {
            $existing = DailyAbsenceReport::where('instructor_assignment_id', $assignment->id)
                ->whereDate('report_date', today());
            if ($absentStudentIds->isEmpty()) {
                $existing->delete();
            } else {
                $existing->whereNotIn('student_id', $absentStudentIds)->delete();
            }
            foreach ($absentStudentIds as $studentId) {
                DailyAbsenceReport::updateOrCreate([
                    'instructor_assignment_id' => $assignment->id,
                    'student_id' => $studentId,
                    'report_date' => today(),
                ], [
                    'comment' => filled($data['comments'][$studentId] ?? null)
                        ? trim($data['comments'][$studentId])
                        : null,
                ]);
            }
        });

        return redirect()->route('student-reports.index', ['assignment_id' => $assignment->id])
            ->with('success', 'Today’s student absence report has been saved.');
    }

    private function studentsFor(InstructorAssignment $assignment): Builder
    {
        return Student::query()
            ->where('course_id', $assignment->course_id)
            ->where(function ($query) use ($assignment) {
                $query->where(function ($regular) use ($assignment) {
                    $regular->where(fn ($type) => $type->whereNull('student_type')->orWhere('student_type', '!=', 'irregular'))
                        ->where('year_level', $assignment->year_level)
                        ->when($assignment->section_id, fn ($students) => $students->where('section_id', $assignment->section_id));
                })->orWhere(function ($irregular) use ($assignment) {
                    $irregular->where('student_type', 'irregular')
                        ->whereHas('subjects', fn ($subjects) => $subjects->whereKey($assignment->subject_id));
                });
            });
    }
}
