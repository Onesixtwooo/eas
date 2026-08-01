<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InstructorAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $editingAssignments = collect();
        if ($request->filled('edit_faculty') && $request->filled('edit_course')) {
            $editingAssignments = InstructorAssignment::where('faculty_id', $request->integer('edit_faculty'))
                ->where('course_id', $request->integer('edit_course'))
                ->get();
            abort_if($editingAssignments->isEmpty(), 404);
        }

        $groupedAssignments = InstructorAssignment::with(['faculty.user', 'course', 'subject'])
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')))
            ->when($request->filled('year_level'), fn ($query) => $query->where('year_level', $request->integer('year_level')))
            ->orderBy('course_id')
            ->orderBy('year_level')
            ->get()
            ->groupBy(fn ($assignment) => "{$assignment->faculty_id}:{$assignment->course_id}")
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $assignments = new LengthAwarePaginator(
            $groupedAssignments->forPage($page, 20)->values(),
            $groupedAssignments->count(),
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.instructor-assignments.index', [
            'assignments' => $assignments,
            'faculty' => Faculty::with('user')->get()->sortBy('user.name'),
            'courses' => Course::where('is_active', true)->orderBy('code')->get(),
            'subjects' => Subject::where('is_active', true)->orderBy('code')->get(),
            'editingAssignments' => $editingAssignments,
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->has('year_levels') && $request->filled('year_level')) {
            $request->merge(['year_levels' => [$request->integer('year_level')]]);
        }

        $data = $request->validate([
            'faculty_id' => ['required', 'exists:faculty,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'year_levels' => ['required', 'array', 'min:1'],
            'year_levels.*' => ['required', 'integer', 'distinct', 'between:1,5'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
        ], [
            'subject_ids.required' => 'Select at least one subject.',
            'subject_ids.min' => 'Select at least one subject.',
        ]);

        $yearLevels = collect($data['year_levels'])->map(fn ($year) => (int) $year)->sort()->values();
        $subjects = Subject::whereIn('id', $data['subject_ids'])
            ->where('course_id', $data['course_id'])
            ->where(function ($query) use ($data) {
                $query->whereNull('year_level')
                    ->orWhereIn('year_level', $data['year_levels']);
            })
            ->get();

        abort_unless($subjects->count() === count($data['subject_ids']), 422, 'Every selected subject must belong to the selected course and one of the selected year levels.');

        $pairs = $subjects->flatMap(function (Subject $subject) use ($yearLevels) {
            $levels = $subject->year_level ? collect([(int) $subject->year_level]) : $yearLevels;

            return $levels->map(fn ($yearLevel) => ['subject_id' => $subject->id, 'year_level' => $yearLevel]);
        });

        $existing = InstructorAssignment::where('course_id', $data['course_id'])
            ->where('faculty_id', $data['faculty_id'])
            ->whereIn('subject_id', $pairs->pluck('subject_id')->unique())
            ->whereIn('year_level', $pairs->pluck('year_level')->unique())
            ->get()
            ->keyBy(fn ($assignment) => "{$assignment->subject_id}:{$assignment->year_level}");

        $created = DB::transaction(function () use ($data, $pairs, $existing) {
            $count = 0;
            foreach ($pairs as $pair) {
                $assignment = $existing->get("{$pair['subject_id']}:{$pair['year_level']}");
                if ($assignment) {
                    $assignment->update(['is_active' => true]);
                    continue;
                }

                InstructorAssignment::create([
                    'faculty_id' => $data['faculty_id'],
                    'course_id' => $data['course_id'],
                    'subject_id' => $pair['subject_id'],
                    'year_level' => $pair['year_level'],
                    'is_active' => true,
                ]);
                $count++;
            }

            return $count;
        });

        $years = $yearLevels->map(fn ($year) => "Year {$year}")->implode(', ');

        return ($request->boolean('_editing_assignment')
            ? redirect()->route('admin.instructor-assignments.index')
            : back())
            ->with('success', "{$created} subject assignment(s) saved for {$years}.");
    }

    public function toggle(InstructorAssignment $assignment)
    {
        $assignment->update(['is_active' => ! $assignment->is_active]);
        return back()->with('success', 'Instructor assignment status updated.');
    }

    public function updateGroup(Request $request, int $faculty, int $course)
    {
        abort_if(! InstructorAssignment::where('faculty_id', $faculty)->where('course_id', $course)->exists(), 404);

        $request->merge(['faculty_id' => $faculty, 'course_id' => $course, '_editing_assignment' => true]);

        return DB::transaction(function () use ($request, $faculty, $course) {
            InstructorAssignment::where('faculty_id', $faculty)->where('course_id', $course)->delete();

            return $this->store($request);
        });
    }

    public function destroy(InstructorAssignment $assignment)
    {
        $assignment->delete();
        return back()->with('success', 'Instructor assignment removed.');
    }

    public function toggleGroup(int $faculty, int $course)
    {
        $query = InstructorAssignment::where('faculty_id', $faculty)
            ->where('course_id', $course);

        abort_if(! $query->exists(), 404);

        $enable = ! (clone $query)->where('is_active', false)->doesntExist();
        $query->update(['is_active' => $enable]);

        return back()->with('success', $enable
            ? 'All subjects in the assignment were enabled.'
            : 'All subjects in the assignment were disabled.');
    }

    public function destroyGroup(int $faculty, int $course)
    {
        $deleted = InstructorAssignment::where('faculty_id', $faculty)
            ->where('course_id', $course)
            ->delete();

        abort_if($deleted === 0, 404);

        return back()->with('success', "{$deleted} subject assignment(s) removed.");
    }
}
