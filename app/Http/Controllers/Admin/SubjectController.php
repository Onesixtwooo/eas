<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubjectRequest;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Http\Request;

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

        $subject->update($data);

        return redirect()->route('admin.subjects.index', ['semester' => $data['semester']])
            ->with('success', "Subject {$data['code']} updated successfully.");
    }
}
