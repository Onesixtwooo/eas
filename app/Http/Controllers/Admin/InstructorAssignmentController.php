<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
use App\Models\Section;
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
                ->whereNull('retired_at')
                ->with('section')
                ->get();
            abort_if($editingAssignments->isEmpty(), 404);
        }

        $groupedAssignments = InstructorAssignment::with(['faculty.user', 'course', 'subject', 'section'])
            ->whereNull('retired_at')
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

    public function store(Request $request, bool $replaceGroup = false)
    {
        if (! $request->has('year_levels') && $request->filled('year_level')) {
            $request->merge(['year_levels' => [$request->integer('year_level')]]);
        }

        $data = $request->validate([
            'faculty_id' => ['required', 'exists:faculty,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'year_levels' => ['required', 'array', 'min:1'],
            'year_levels.*' => ['required', 'integer', 'distinct', 'between:1,5'],
            'block_names' => ['nullable', 'array', 'min:1'],
            'block_names.*' => ['required', 'string', 'distinct', 'in:all,A,B,C,D,E,F,G'],
            'subject_blocks' => ['nullable', 'array'],
            'subject_blocks.*' => ['required', 'array', 'min:1'],
            'subject_blocks.*.*' => ['required', 'string', 'in:all,A,B,C,D,E,F,G'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
        ], [
            'subject_ids.required' => 'Select at least one subject.',
            'subject_ids.min' => 'Select at least one subject.',
        ]);

        $yearLevels = collect($data['year_levels'])->map(fn ($year) => (int) $year)->sort()->values();
        $perSubjectBlocks = $request->boolean('subject_block_selection_submitted') || $request->has('subject_blocks');
        if ($perSubjectBlocks && $request->has('block_names')) {
            return back()->withErrors(['subject_blocks' => 'Choose blocks separately for each subject.'])->withInput();
        }
        if ($request->boolean('block_selection_submitted') && ! $request->has('block_names')) {
            return back()->withErrors(['block_names' => 'Select All Blocks or at least one specific block.'])->withInput();
        }
        if ($replaceGroup && ! $perSubjectBlocks && ! $request->has('block_names') && InstructorAssignment::where('faculty_id', $data['faculty_id'])
            ->where('course_id', $data['course_id'])->whereNull('retired_at')->whereNotNull('section_id')->exists()) {
            return back()->withErrors(['block_names' => 'Choose blocks explicitly when editing block-specific assignments.'])->withInput();
        }
        $subjectBlocks = collect($data['subject_ids'])->mapWithKeys(function ($subjectId) use ($data, $perSubjectBlocks) {
            $blocks = $perSubjectBlocks
                ? ($data['subject_blocks'][$subjectId] ?? [])
                : ($data['block_names'] ?? ['all']);

            return [$subjectId => collect($blocks)];
        });
        foreach ($subjectBlocks as $subjectId => $blocks) {
            if ($blocks->isEmpty()) {
                return back()->withErrors(["subject_blocks.{$subjectId}" => 'Select at least one block for each subject.'])->withInput();
            }
            if ($blocks->unique()->count() !== $blocks->count()) {
                return back()->withErrors(["subject_blocks.{$subjectId}" => 'Select each block only once for this subject.'])->withInput();
            }
            if ($blocks->contains('all') && $blocks->count() > 1) {
                $key = $perSubjectBlocks ? "subject_blocks.{$subjectId}" : 'block_names';
                return back()->withErrors([$key => 'Choose All Blocks or specific blocks for this subject, not both.'])->withInput();
            }
        }
        $subjects = Subject::whereIn('id', $data['subject_ids'])
            ->where('course_id', $data['course_id'])
            ->where(function ($query) use ($data) {
                $query->whereNull('year_level')
                    ->orWhereIn('year_level', $data['year_levels']);
            })
            ->get();

        if ($subjects->count() !== count($data['subject_ids'])) {
            return back()->withErrors([
                'subject_ids' => 'Every selected subject must belong to the selected course and one of the selected year levels.',
            ])->withInput();
        }

        $sections = Section::where('course_id', $data['course_id'])
            ->whereIn('year_level', $yearLevels)
            ->whereIn('name', $subjectBlocks->flatMap(fn ($blocks) => $blocks)->reject(fn ($block) => $block === 'all')->unique())
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($section) => "{$section->year_level}:{$section->name}");

        $pairs = $subjects->flatMap(function (Subject $subject) use ($yearLevels, $subjectBlocks, $sections) {
            $levels = $subject->year_level ? collect([(int) $subject->year_level]) : $yearLevels;
            $blocks = $subjectBlocks->get($subject->id);

            return $levels->flatMap(fn ($yearLevel) => $blocks->map(fn ($block) => [
                'subject_id' => $subject->id,
                'year_level' => $yearLevel,
                'section_id' => $block === 'all' ? null : $sections->get("{$yearLevel}:{$block}")?->id,
                'block' => $block,
            ]));
        });
        $missingSection = $pairs->first(fn ($pair) => $pair['block'] !== 'all' && $pair['section_id'] === null);
        if ($missingSection) {
            $key = $perSubjectBlocks ? "subject_blocks.{$missingSection['subject_id']}" : 'block_names';
            return back()->withErrors([$key => 'Every selected block must exist for the chosen course and year levels.'])->withInput();
        }
        $pairs = $pairs->map(fn ($pair) => collect($pair)->except('block')->all());

        $created = DB::transaction(function () use ($data, $pairs, $replaceGroup, $perSubjectBlocks) {
            $this->lockCourse($data['course_id']);
            $existing = InstructorAssignment::where('course_id', $data['course_id'])
                ->where('faculty_id', $data['faculty_id'])
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($assignment) => "{$assignment->subject_id}:{$assignment->year_level}:".($assignment->section_id ?? 'all'));
            $desiredKeys = $pairs->map(fn ($pair) => "{$pair['subject_id']}:{$pair['year_level']}:".($pair['section_id'] ?? 'all'));

            if ($replaceGroup) {
                foreach ($existing as $key => $assignment) {
                    if ($assignment->retired_at || $desiredKeys->contains($key)) {
                        continue;
                    }
                    $this->retireOrDelete($assignment);
                }
            }

            $count = 0;
            foreach ($pairs as $pair) {
                $assignment = $existing->get("{$pair['subject_id']}:{$pair['year_level']}:".($pair['section_id'] ?? 'all'));
                $overlap = InstructorAssignment::where('course_id', $data['course_id'])
                    ->where('subject_id', $pair['subject_id'])
                    ->where('year_level', $pair['year_level'])
                    ->where('is_active', true)
                    ->whereNull('retired_at')
                    ->when($assignment, fn ($query) => $query->whereKeyNot($assignment->id))
                    ->when($pair['section_id'], fn ($query) => $query->where(fn ($scope) => $scope
                        ->whereNull('section_id')
                        ->orWhere('section_id', $pair['section_id'])))
                    ->lockForUpdate()
                    ->first(['id']) !== null;
                if ($overlap) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $perSubjectBlocks ? 'subject_blocks' : 'block_names' => 'This subject and year level already has an active instructor for one of the selected blocks.',
                    ]);
                }
                if ($assignment) {
                    $assignment->update(['is_active' => true, 'retired_at' => null]);
                    continue;
                }

                InstructorAssignment::create([
                    'faculty_id' => $data['faculty_id'],
                    'course_id' => $data['course_id'],
                    'subject_id' => $pair['subject_id'],
                    'year_level' => $pair['year_level'],
                    'section_id' => $pair['section_id'],
                    'is_active' => true,
                ]);
                $count++;
            }

            return $count;
        });

        $years = $yearLevels->map(fn ($year) => "Year {$year}")->implode(', ');

        return ($replaceGroup
            ? redirect()->route('admin.instructor-assignments.index')
            : back())
            ->with('success', "{$created} subject assignment(s) saved for {$years}.");
    }

    public function toggle(InstructorAssignment $assignment)
    {
        DB::transaction(function () use ($assignment) {
            $this->lockCourse($assignment->course_id);
            $current = InstructorAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_if($current->retired_at, 404);
            if (! $current->is_active && $this->hasActiveOverlap($current)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'block_names' => 'This subject and block already has an active instructor.',
                ]);
            }
            $current->update(['is_active' => ! $current->is_active]);
        });

        return back()->with('success', 'Instructor assignment status updated.');
    }

    public function updateGroup(Request $request, int $faculty, int $course)
    {
        abort_if(! InstructorAssignment::where('faculty_id', $faculty)->where('course_id', $course)->whereNull('retired_at')->exists(), 404);

        $request->merge(['faculty_id' => $faculty, 'course_id' => $course]);

        return $this->store($request, true);
    }

    public function destroy(InstructorAssignment $assignment)
    {
        DB::transaction(function () use ($assignment) {
            $this->lockCourse($assignment->course_id);
            $current = InstructorAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_if($current->retired_at, 404);
            $this->retireOrDelete($current);
        });
        return back()->with('success', 'Instructor assignment removed.');
    }

    public function toggleGroup(int $faculty, int $course)
    {
        $enable = DB::transaction(function () use ($faculty, $course) {
            $this->lockCourse($course);
            $assignments = InstructorAssignment::where('faculty_id', $faculty)
                ->where('course_id', $course)
                ->whereNull('retired_at')
                ->lockForUpdate()
                ->get();
            abort_if($assignments->isEmpty(), 404);

            $enable = $assignments->contains(fn ($assignment) => ! $assignment->is_active);
            $overlapWithinGroup = $assignments->contains(fn ($assignment) => $assignments->contains(fn ($other) =>
                $other->id !== $assignment->id
                    && $other->subject_id === $assignment->subject_id
                    && $other->year_level === $assignment->year_level
                    && ($other->section_id === null || $assignment->section_id === null || $other->section_id === $assignment->section_id)
            ));
            if ($enable && ($overlapWithinGroup || $assignments->contains(fn ($assignment) => $this->hasActiveOverlap($assignment)))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'block_names' => 'An assignment in this group overlaps an active instructor for the same subject and block.',
                ]);
            }
            InstructorAssignment::whereKey($assignments->modelKeys())->update(['is_active' => $enable]);

            return $enable;
        });

        return back()->with('success', $enable
            ? 'All subjects in the assignment were enabled.'
            : 'All subjects in the assignment were disabled.');
    }

    public function destroyGroup(int $faculty, int $course)
    {
        $deleted = DB::transaction(function () use ($faculty, $course) {
            $this->lockCourse($course);
            $assignments = InstructorAssignment::where('faculty_id', $faculty)
                ->where('course_id', $course)
                ->whereNull('retired_at')
                ->lockForUpdate()
                ->get();
            foreach ($assignments as $assignment) {
                $this->retireOrDelete($assignment);
            }

            return $assignments->count();
        });

        abort_if($deleted === 0, 404);

        return back()->with('success', "{$deleted} subject assignment(s) removed.");
    }

    private function retireOrDelete(InstructorAssignment $assignment): void
    {
        if ($assignment->dailyAbsenceReports()->exists()) {
            $assignment->update(['is_active' => false, 'retired_at' => now()]);
        } else {
            $assignment->delete();
        }
    }

    private function lockCourse(int $courseId): void
    {
        Course::whereKey($courseId)->lockForUpdate()->firstOrFail();
    }

    private function hasActiveOverlap(InstructorAssignment $assignment): bool
    {
        return InstructorAssignment::where('course_id', $assignment->course_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('year_level', $assignment->year_level)
            ->whereKeyNot($assignment->id)
            ->where('is_active', true)
            ->whereNull('retired_at')
            ->when($assignment->section_id, fn ($query) => $query->where(fn ($scope) => $scope
                ->whereNull('section_id')
                ->orWhere('section_id', $assignment->section_id)))
            ->lockForUpdate()
            ->first(['id']) !== null;
    }
}
