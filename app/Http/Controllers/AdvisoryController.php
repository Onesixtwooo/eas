<?php

namespace App\Http\Controllers;

use App\Models\DailyAbsenceReport;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdvisoryController extends Controller
{
    public function index(Request $request)
    {
        $yearLevel = $request->user()->adviser_year_level;
        abort_unless($yearLevel, 403, 'No advisory year level has been assigned to this account.');

        $sections = $this->sectionsFor($yearLevel);

        if ($request->filled('section_id')) {
            abort_unless($sections->contains('id', $request->integer('section_id')), 404);
        }

        $students = Student::query()
            ->with(['user', 'course', 'section'])
            ->where('year_level', $yearLevel)
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
            ->orderBy('section_id')
            ->orderBy('student_number')
            ->paginate(20)
            ->withQueryString();

        return view('advisory.index', compact('students', 'sections', 'yearLevel'));
    }

    public function absences(Request $request)
    {
        $selectedDate = $request->validate([
            'report_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])['report_date'] ?? today()->toDateString();
        $canRespond = $selectedDate === today()->toDateString();
        $yearLevel = $request->user()->adviser_year_level;
        abort_unless($yearLevel, 403, 'No advisory year level has been assigned to this account.');

        $sections = $this->sectionsFor($yearLevel, $selectedDate);
        if ($request->filled('section_id')) {
            abort_unless($sections->contains('id', $request->integer('section_id')), 404);
        }

        $visibleReports = fn ($reports) => $reports
            ->whereDate('report_date', $selectedDate)
            ->where('reported_year_level', $yearLevel)
            ->when($request->filled('section_id'), fn ($query) => $query->where('reported_section_id', $request->integer('section_id')))
            ->where(fn ($query) => $query
                ->whereIn('attendance_status', ['absent', 'late'])
                ->orWhereNotNull('adviser_comment')
                ->orWhereNotNull('adviser_action'));
        $absentStudents = Student::query()
            ->with([
                'user', 'course', 'section',
                'dailyAbsenceReports' => fn ($reports) => $visibleReports($reports)
                    ->with(['assignment.subject', 'assignment.faculty.user']),
            ])
            ->whereHas('dailyAbsenceReports', $visibleReports)
            ->orderBy('student_number')
            ->get();

        return view('advisory.absences', compact('absentStudents', 'sections', 'yearLevel', 'selectedDate', 'canRespond'));
    }

    public function comment(Request $request, DailyAbsenceReport $report)
    {
        $this->authorizeReport($request, $report);

        $data = $request->validate([
            'adviser_comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $comment = filled($data['adviser_comment'] ?? null) ? trim($data['adviser_comment']) : null;
        $report->update([
            'adviser_comment' => $comment,
            'adviser_commented_by' => $comment ? $request->user()->id : null,
            'adviser_commented_at' => $comment ? now() : null,
        ]);

        return back()->with('success', $comment ? 'Your adviser comment has been saved.' : 'Your adviser comment has been removed.');
    }

    public function action(Request $request, DailyAbsenceReport $report)
    {
        $this->authorizeReport($request, $report);
        $data = $request->validate([
            'adviser_action' => ['required', Rule::in(['acknowledged', 'resolved', 'flagged_incorrect'])],
        ]);
        $report->update([
            'adviser_action' => $data['adviser_action'],
            'adviser_action_by' => $request->user()->id,
            'adviser_action_at' => now(),
        ]);

        $label = match ($data['adviser_action']) {
            'acknowledged' => 'acknowledged',
            'resolved' => 'marked as resolved',
            default => 'flagged as incorrect',
        };

        return back()->with('success', "The absence report has been {$label}.");
    }

    private function authorizeReport(Request $request, DailyAbsenceReport $report): void
    {
        $yearLevel = $request->user()->adviser_year_level;
        abort_unless(
            $yearLevel
                && $report->reported_year_level === $yearLevel
                && $report->report_date->isToday(),
            403
        );
    }

    private function sectionsFor(int $yearLevel, ?string $reportDate = null)
    {
        return Section::query()
            ->with('course')
            ->where('year_level', $yearLevel)
            ->where(function ($query) use ($yearLevel, $reportDate) {
                $query->where(fn ($current) => $current
                    ->where('is_active', true)
                    ->whereIn('id', Student::query()
                        ->where('year_level', $yearLevel)
                        ->whereNotNull('section_id')
                        ->select('section_id')));
                if ($reportDate) {
                    $query->orWhereIn('id', DailyAbsenceReport::query()
                        ->where('reported_year_level', $yearLevel)
                        ->whereDate('report_date', $reportDate)
                        ->whereNotNull('reported_section_id')
                        ->select('reported_section_id'));
                }
            })
            ->orderBy('name')
            ->get();
    }
}
