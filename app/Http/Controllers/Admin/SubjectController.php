<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubjectRequest;
use App\Models\Course;
use App\Models\InstructorAssignment;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $semester = in_array($request->integer('semester'), [1, 2], true)
            ? $request->integer('semester')
            : 1;

        return view('admin.subjects.index', [
            'subjects' => Subject::with('course')
                ->where('semester', $semester)
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhereHas('course', fn ($course) => $course
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%"));
                    });
                })
                ->when($request->filled('year_level'), fn ($query) => $query->where('year_level', $request->integer('year_level')))
                ->orderBy('course_id')
                ->orderBy('year_level')
                ->orderBy('code')
                ->paginate(20)
                ->withQueryString(),
            'semester' => $semester,
        ]);
    }

    public function create()
    {
        return view('admin.subjects.create', [
            'courses' => Course::where('is_active', true)->orderBy('code')->get(),
            'subject' => new Subject,
        ]);
    }

    public function store(StoreSubjectRequest $request)
    {
        $data = $request->validated();
        $data['code'] = strtoupper(trim($data['code']));
        $data['name'] = trim($data['name']);
        $data['is_active'] = true;

        Subject::create($data);

        return redirect()->route('admin.subjects.index', ['semester' => $data['semester']])
            ->with('success', "Subject {$data['code']} added for Year {$data['year_level']}.");
    }

    public function edit(Subject $subject)
    {
        return view('admin.subjects.create', [
            'courses' => Course::where('is_active', true)->orderBy('code')->get(),
            'subject' => $subject,
        ]);
    }

    public function update(StoreSubjectRequest $request, Subject $subject)
    {
        $data = $request->validated();
        $data['code'] = strtoupper(trim($data['code']));
        $data['name'] = trim($data['name']);

        DB::transaction(function () use ($subject, $data) {
            Course::whereKey($subject->course_id)->lockForUpdate()->firstOrFail();
            $assignments = InstructorAssignment::where('subject_id', $subject->id)
                ->whereNull('retired_at')->with('section')->lockForUpdate()->get();

            if ($assignments->isNotEmpty() && (int) $data['course_id'] !== (int) $subject->course_id) {
                throw ValidationException::withMessages([
                    'course_id' => 'Remove this subject from its instructor assignments before changing its course.',
                ]);
            }

            if ($assignments->contains(fn ($assignment) => (int) $assignment->year_level !== (int) $data['year_level'])) {
                $targetYear = (int) $data['year_level'];
                $sections = Section::where('course_id', $subject->course_id)
                    ->where('year_level', $targetYear)->where('is_active', true)
                    ->get()->keyBy('name');
                $targets = $assignments->map(function (InstructorAssignment $assignment) use ($sections, $targetYear, $subject) {
                    if ((int) $assignment->year_level === $targetYear) {
                        return ['source' => $assignment, 'keep' => true];
                    }
                    $section = $assignment->section_id ? $sections->get($assignment->section?->name) : null;
                    if ($assignment->section_id && ! $section) {
                        throw ValidationException::withMessages([
                            'year_level' => 'The target year does not have every block used by this subject’s instructor assignments.',
                        ]);
                    }

                    $overlap = InstructorAssignment::where('course_id', $subject->course_id)
                        ->where('subject_id', $subject->id)->where('year_level', $targetYear)
                        ->whereNull('retired_at')->where('is_active', true)
                        ->when($section, fn ($query) => $query->where(fn ($scope) => $scope
                            ->whereNull('section_id')->orWhere('section_id', $section->id)))
                        ->lockForUpdate()->exists();
                    if ($assignment->is_active && $overlap) {
                        throw ValidationException::withMessages([
                            'year_level' => 'The target year already has an active instructor for one of this subject’s blocks.',
                        ]);
                    }

                    return ['source' => $assignment, 'section_id' => $section?->id, 'is_active' => $assignment->is_active];
                });

                $moving = $targets->reject(fn ($target) => $target['keep'] ?? false)->values();
                foreach ($moving as $index => $target) {
                    foreach ($moving->slice($index + 1) as $other) {
                        $sameBlock = $target['section_id'] === $other['section_id'];
                        $overlap = $target['section_id'] === null || $other['section_id'] === null || $sameBlock;
                        if (($sameBlock && $target['source']->faculty_id === $other['source']->faculty_id)
                            || ($overlap && $target['is_active'] && $other['is_active'])) {
                            throw ValidationException::withMessages([
                                'year_level' => 'These instructor assignments would overlap in the target year.',
                            ]);
                        }
                    }
                }

                foreach ($targets as $target) {
                    if ($target['keep'] ?? false) {
                        continue;
                    }
                    $source = $target['source'];
                    $existing = InstructorAssignment::where('faculty_id', $source->faculty_id)
                        ->where('course_id', $source->course_id)
                        ->where('subject_id', $source->subject_id)
                        ->where('year_level', $targetYear)
                        ->where('section_id', $target['section_id'])
                        ->lockForUpdate()->first();
                    if ($existing && ! $existing->retired_at) {
                        throw ValidationException::withMessages([
                            'year_level' => 'This instructor already has this subject and block in the target year.',
                        ]);
                    }
                }

                foreach ($targets as $target) {
                    if ($target['keep'] ?? false) {
                        continue;
                    }
                    $source = $target['source'];
                    if ($source->dailyAbsenceReports()->exists()) {
                        $source->update(['is_active' => false, 'retired_at' => now()]);
                    } else {
                        $source->delete();
                    }

                    $existing = InstructorAssignment::where('faculty_id', $source->faculty_id)
                        ->where('course_id', $source->course_id)
                        ->where('subject_id', $source->subject_id)
                        ->where('year_level', $targetYear)
                        ->where('section_id', $target['section_id'])
                        ->whereNotNull('retired_at')->lockForUpdate()->first();
                    if ($existing) {
                        $existing->update(['is_active' => $target['is_active'], 'retired_at' => null]);
                    } else {
                        InstructorAssignment::create([
                            'faculty_id' => $source->faculty_id,
                            'course_id' => $source->course_id,
                            'subject_id' => $source->subject_id,
                            'year_level' => $targetYear,
                            'section_id' => $target['section_id'],
                            'is_active' => $target['is_active'],
                        ]);
                    }
                }
            }

            $subject->update($data);
        });

        return redirect()->route('admin.subjects.index', ['semester' => $data['semester']])
            ->with('success', "Subject {$data['code']} updated successfully.");
    }
}
