<?php

namespace App\Http\Controllers;

use App\Exceptions\VirusScanException;
use App\Http\Requests\StoreExcuseRequest;
use App\Models\AcademicYear;
use App\Models\ExcuseRequest;
use App\Models\InstructorAssignment;
use App\Models\ReasonCategory;
use App\Models\Semester;
use App\Models\Section;
use App\Models\Student;
use App\Models\SupportingDocument;
use App\Models\SystemSetting;
use App\Services\RequestWorkflowService;
use App\Services\ExcuseRequestArchiveService;
use App\Services\VirusScanner;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExcuseRequestController extends Controller
{
    private const FACULTY_VISIBLE_STATUSES = ['approved', 'acknowledged', 'completed'];

    private function scanUploadedDocument(UploadedFile $file, string $attribute = 'document'): void
    {
        try {
            app(VirusScanner::class)->scan($file->getRealPath());
        } catch (VirusScanException $exception) {
            Log::warning('Document antivirus scan rejected an upload.', [
                'infected' => $exception->infected,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $attribute => $exception->infected
                    ? 'The supporting document was rejected because malware was detected.'
                    : 'The supporting document could not be safely scanned. Please try again later.',
            ]);
        }
    }

    private function permitted(ExcuseRequest $e): bool
    {
        $u = auth()->user();

        return $u->role === 'admin' || $u->role === 'program_head' || ($u->role === 'student' && $u->student && $e->student_id === $u->student->id) || ($u->role === 'faculty' && $u->faculty && in_array($e->status, self::FACULTY_VISIBLE_STATUSES, true) && ($e->facilitator_id === $u->faculty->id || $e->facilitators()->whereKey($u->faculty->id)->exists()));
    }

    public function index(Request $r)
    {
        $u = auth()->user();
        abort_unless(in_array($u->role, ['admin', 'program_head', 'faculty', 'student'], true), 403);
        $assignedSubjects = collect();
        $selectedSubjectId = null;
        $yearLevels = collect();
        $selectedYearLevel = null;
        $pendingCountsByYear = collect();
        $pendingCountTotal = 0;
        if ($u->role === 'faculty') {
            abort_unless($u->faculty, 403, 'This faculty account is not linked to a faculty profile.');
            $assignedSubjects = InstructorAssignment::query()
                ->with('subject.course')
                ->where('faculty_id', $u->faculty->id)
                ->where('is_active', true)
                ->get()
                ->pluck('subject')
                ->filter()
                ->unique('id')
                ->sortBy('code')
                ->values();
            if ($r->filled('subject_id')) {
                $selectedSubjectId = $r->integer('subject_id');
                abort_unless($assignedSubjects->contains('id', $selectedSubjectId), 404);
            }
        }
        if (in_array($u->role, ['admin', 'program_head'], true)) {
            $yearLevels = Section::query()
                ->where('is_active', true)
                ->distinct()
                ->orderBy('year_level')
                ->pluck('year_level')
                ->concat(ExcuseRequest::whereNotNull('reported_year_level')->distinct()->pluck('reported_year_level'))
                ->unique()->sort()->values();
            if ($yearLevels->isEmpty()) {
                $yearLevels = collect(range(1, 5));
            }
            if ($r->filled('year_level')) {
                $selectedYearLevel = $r->integer('year_level');
                abort_unless($yearLevels->contains($selectedYearLevel), 404);
            }
            $pendingCountsByYear = ExcuseRequest::query()
                ->join('students', 'students.id', '=', 'excuse_requests.student_id')
                ->whereIn('excuse_requests.status', ['submitted', 'under_review'])
                ->selectRaw('coalesce(excuse_requests.reported_year_level, students.year_level) as year_level, count(*) as total')
                ->groupByRaw('coalesce(excuse_requests.reported_year_level, students.year_level)')
                ->pluck('total', 'year_level');
            $pendingCountTotal = (int) $pendingCountsByYear->sum();
        }
        $applyFilters = function ($q) use ($r, $u, $selectedSubjectId, $selectedYearLevel) {
            if (in_array($u->role, ['admin', 'program_head'], true) && $selectedYearLevel) {
                $q->where(fn ($requests) => $requests
                    ->where('reported_year_level', $selectedYearLevel)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('reported_year_level')
                        ->whereHas('student', fn ($student) => $student->where('year_level', $selectedYearLevel))));
            }
            if ($u->role === 'faculty') {
                $q->whereIn('status', self::FACULTY_VISIBLE_STATUSES)
                    ->where(fn ($x) => $x->where('facilitator_id', $u->faculty->id)->orWhereHas('facilitators', fn ($y) => $y->whereKey($u->faculty->id)));
                if ($selectedSubjectId) {
                    $q->where(fn ($request) => $request
                        ->where('subject_id', $selectedSubjectId)
                        ->orWhereHas('subjects', fn ($subject) => $subject->whereKey($selectedSubjectId)));
                }
            }
            if ($u->role === 'student' && $r->filled('status')) {
                $q->where('status', $r->status);
            }
            if ($r->filled('search')) {
                $search = trim($r->search);
                $q->where(fn ($x) => $x->where('reference_number', 'like', '%'.$search.'%')
                    ->orWhere('legacy_reference_number', 'like', '%'.$search.'%')
                    ->orWhereHas('subjects', fn ($y) => $y->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('subject', fn ($y) => $y->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('student', fn ($y) => $y->where('student_number', 'like', '%'.$search.'%')->orWhereHas('user', fn ($z) => $z->where('name', 'like', '%'.$search.'%'))));
            }
        };

        if ($u->role === 'student') {
            $q = ExcuseRequest::with(['student.user', 'subject', 'facilitator.user', 'subjects', 'facilitators.user'])
                ->where('student_id', $u->student->id);
            $applyFilters($q);
            $requests = $q->latest()->paginate(15)->withQueryString();
            $studentGroups = null;
            $historyPages = null;
        } else {
            $focusedStudentId = $r->filled('student_id') ? $r->integer('student_id') : null;
            $selectedStatus = $r->input('status', $u->role === 'faculty' ? 'confirmed' : 'pending');
            $groupFilters = function ($q) use ($applyFilters, $selectedStatus) {
                $applyFilters($q);
                if ($selectedStatus === 'pending') {
                    $q->whereIn('status', ['submitted', 'under_review']);
                } elseif ($selectedStatus === 'confirmed') {
                    $q->whereIn('status', self::FACULTY_VISIBLE_STATUSES);
                } elseif ($selectedStatus !== 'all') {
                    $q->where('status', $selectedStatus);
                }
            };
            $countScope = $applyFilters;
            $approvedStatuses = $u->role === 'faculty' ? self::FACULTY_VISIBLE_STATUSES : ['approved'];
            $studentGroups = Student::query()
                ->whereHas('requests', $groupFilters)
                ->when($focusedStudentId, fn ($query) => $query->whereKey($focusedStudentId))
                ->with('user')
                ->withCount(['requests as requests_count' => $countScope])
                ->withCount(['requests as visible_requests_count' => $groupFilters])
                ->withCount(['requests as pending_requests_count' => function ($q) use ($countScope) { $countScope($q); $q->whereIn('status', ['submitted', 'under_review']); }])
                ->withCount(['requests as approved_requests_count' => function ($q) use ($countScope, $approvedStatuses) { $countScope($q); $q->whereIn('status', $approvedStatuses); }])
                ->when(! $focusedStudentId, fn ($query) => $query->with(['requests' => function ($q) use ($groupFilters) {
                    $groupFilters($q);
                    $q->with(['subject', 'facilitator.user', 'subjects', 'facilitators.user'])
                        ->latest()->orderByDesc('id')->limit(10);
                }]))
                ->withMax(['requests as latest_request_at' => $groupFilters], 'created_at')
                ->orderByDesc('latest_request_at')
                ->orderByDesc('students.id')
                ->paginate(15)->withQueryString();
            $historyPages = null;
            if ($focusedStudentId && $studentGroups->isNotEmpty()) {
                $historyQuery = ExcuseRequest::query()
                    ->where('student_id', $focusedStudentId)
                    ->with(['subject', 'facilitator.user', 'subjects', 'facilitators.user']);
                $groupFilters($historyQuery);
                $historyPages = $historyQuery->latest()->orderByDesc('id')
                    ->paginate(15, ['*'], 'request_page')->withQueryString();
                $studentGroups->first()->setRelation('requests', $historyPages->getCollection());
            }
            $requests = null;
        }
        $programHeadName = SystemSetting::valueFor('program_head_name', 'PRINCESS LEA ANN D. CALINA, MSIT');

        return view('requests.index', compact('requests', 'studentGroups', 'historyPages', 'programHeadName', 'assignedSubjects', 'selectedSubjectId', 'yearLevels', 'selectedYearLevel', 'pendingCountsByYear', 'pendingCountTotal'));
    }

    public function create()
    {
        $student = auth()->user()->student;
        abort_unless($student, 403, 'A valid student profile is required.');
        $assignments = $this->assignmentsFor($student);

        return view('requests.form', ['requestItem' => new ExcuseRequest, 'assignments' => $assignments, 'reasons' => ReasonCategory::where('is_active', true)->get()]);
    }

    public function store(StoreExcuseRequest $r, RequestWorkflowService $flow)
    {
        $data = $r->validated();
        $subjectIds = collect($data['subject_ids'])->map(fn ($id) => (int) $id)->values();
        unset($data['subject_ids'],$data['document'],$data['declaration'],$data['intent']);
        $student = $r->user()->student;
        abort_unless($student, 403, 'A valid student profile is required.');
        $assignments = $this->assignmentsFor($student)->whereIn('subject_id', $subjectIds)->keyBy('subject_id');
        if ($assignments->count() !== $subjectIds->count()) {
            return back()->withErrors(['subject_ids' => 'Every selected subject must be in your current enrollment and have an active instructor assignment.'])->withInput();
        }
        $academicYearId = AcademicYear::where('is_current', true)->value('id');
        $semesterId = Semester::where('is_current', true)->value('id');
        if (! $academicYearId || ! $semesterId) {
            throw ValidationException::withMessages(['academic_period' => 'The current academic year and semester have not been configured. Please contact the administrator.']);
        }
        $file = $r->file('document');
        if ($file) {
            $this->scanUploadedDocument($file);
        }
        $primary = $assignments->get($subjectIds->first());
        $data += ['student_id' => $student->id, 'subject_id' => $primary->subject_id, 'facilitator_id' => $primary->faculty_id, 'academic_year_id' => $academicYearId, 'semester_id' => $semesterId, 'status' => 'draft'];
        $storedPath = null;
        try {
            $result = DB::transaction(function () use ($r, $data, $subjectIds, $assignments, $student, $file, $flow, &$storedPath) {
                $lockedStudent = Student::whereKey($student->id)->with(['section', 'course'])->lockForUpdate()->firstOrFail();
                if ($lockedStudent->year_level !== $student->year_level || $lockedStudent->section_id !== $student->section_id) {
                    throw ValidationException::withMessages(['subject_ids' => 'Your academic placement changed. Reload the form and choose your current subjects.']);
                }
                $existing = ExcuseRequest::where('student_id', $student->id)
                    ->whereDate('absence_date', $data['absence_date'])
                    ->where('status', '!=', 'cancelled')
                    ->where(fn ($q) => $q->whereIn('subject_id', $subjectIds)
                        ->orWhereHas('subjects', fn ($s) => $s->whereIn('subjects.id', $subjectIds)))
                    ->latest()->lockForUpdate()->first();
                if ($existing) {
                    return ['existing' => $existing];
                }

                $item = ExcuseRequest::create($data + [
                    'reported_year_level' => $lockedStudent->year_level,
                    'reported_section_id' => $lockedStudent->section_id,
                    'reported_section_name' => $lockedStudent->section?->name,
                    'reported_course_code' => $lockedStudent->course?->code,
                ]);
                $item->subjects()->attach($subjectIds->mapWithKeys(fn ($id) => [$id => ['facilitator_id' => $assignments->get($id)->faculty_id]])->all());
                $item->histories()->create(['new_status' => 'draft', 'action_by' => $r->user()->id, 'remarks' => 'Request created for '.$subjectIds->count().' subject(s)']);
                if ($file) {
                    $storedPath = $file->store('supporting-documents', 'local');
                    if (! $storedPath) {
                        throw ValidationException::withMessages(['document' => 'The attachment could not be stored. Please try again.']);
                    }
                    $item->documents()->create([
                        'disk' => 'local', 'path' => $storedPath,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                    ]);
                }
                if ($r->intent === 'submit') {
                    $flow->transition($item, 'submitted');
                }

                return ['item' => $item];
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }
        if (isset($result['existing'])) {
            return redirect()->route('requests.show', $result['existing'])->with('error', 'A request already exists for one or more selected subjects on this absence date.');
        }
        $item = $result['item'];

        return redirect()->route('requests.show', $item)->with('success', 'Your request for '.$subjectIds->count().' subject(s) has been saved.');
    }

    public function show(ExcuseRequest $excuseRequest)
    {
        abort_unless($this->permitted($excuseRequest), 403);
        $excuseRequest->load(['student.user', 'student.course', 'student.section', 'subject', 'facilitator.user', 'subjects', 'facilitators.user', 'reasonCategory', 'histories.actor', 'documents']);
        $assignments = auth()->user()->role === 'student' && in_array($excuseRequest->status, ['submitted', 'returned'], true) ? $this->assignmentsFor(auth()->user()->student) : collect();

        return view('requests.show', ['item' => $excuseRequest, 'assignments' => $assignments]);
    }

    public function edit(ExcuseRequest $excuseRequest)
    {
        $student = auth()->user()->student;
        abort_unless($student && $excuseRequest->student_id === $student->id, 403);
        abort_unless(in_array($excuseRequest->status, ['submitted', 'returned'], true), 403);
        abort_unless($excuseRequest->placementMatchesStudent(), 403);
        $excuseRequest->load(['documents', 'subjects']);

        return view('requests.edit', ['item' => $excuseRequest, 'assignments' => $this->assignmentsFor($student), 'reasons' => ReasonCategory::where('is_active', true)->get()]);
    }

    public function document(SupportingDocument $document)
    {
        $document->load('excuseRequest.student.user');
        abort_unless($this->permitted($document->excuseRequest), 403);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        $mimeType = Storage::disk($document->disk)->mimeType($document->path) ?: $document->mime_type;

        return Storage::disk($document->disk)->response($document->path, $document->original_name, [
            'Content-Type' => $mimeType,
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateAttachment(Request $r, ExcuseRequest $excuseRequest)
    {
        $student = $r->user()->student;
        abort_unless($student && $excuseRequest->student_id === $student->id, 403);
        $data = $r->validate(['document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $file = $data['document'];
        $this->scanUploadedDocument($file);
        $path = null;
        try {
            $oldDocuments = DB::transaction(function () use ($r, $excuseRequest, $student, $file, &$path) {
                $current = ExcuseRequest::whereKey($excuseRequest->id)->lockForUpdate()->firstOrFail();
                abort_unless($current->student_id === $student->id && $current->status === 'draft', 403);

                $path = $file->store('supporting-documents', 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['document' => 'The attachment could not be stored. Please try again.']);
                }
                $oldDocuments = $current->documents()->get();
                $current->documents()->create([
                    'disk' => 'local', 'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                ]);
                $current->documents()->whereKey($oldDocuments->modelKeys())->delete();
                $current->histories()->create([
                    'previous_status' => 'draft', 'new_status' => 'draft',
                    'action_by' => $r->user()->id,
                    'remarks' => 'Supporting attachment replaced by student.',
                ]);

                return $oldDocuments;
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        foreach ($oldDocuments as $oldDocument) {
            Storage::disk($oldDocument->disk)->delete($oldDocument->path);
        }

        return back()->with('success', 'Supporting attachment changed successfully.');
    }

    public function update(Request $r, ExcuseRequest $excuseRequest)
    {
        $student = $r->user()->student;
        abort_unless($student && $excuseRequest->student_id === $student->id, 403);
        abort_unless(in_array($excuseRequest->status, ['submitted', 'returned'], true), 403);
        abort_unless($excuseRequest->placementMatchesStudent(), 403);
        if (! $r->has('subject_ids') && $r->filled('subject_id')) {
            $r->merge(['subject_ids' => [$r->input('subject_id')]]);
        }
        $data = $r->validate(['absence_date' => 'required|date|before_or_equal:today', 'subject_ids' => 'required|array|min:1', 'subject_ids.*' => 'integer|distinct|exists:subjects,id', 'reason_category_id' => 'required|exists:reason_categories,id', 'explanation' => 'required|string|min:20|max:3000', 'start_time' => 'nullable|date_format:H:i', 'end_time' => 'nullable|date_format:H:i|after:start_time', 'guardian_name' => 'nullable|string|max:255', 'guardian_contact' => 'nullable|string|max:30', 'document' => 'prohibited']);
        $subjectIds = collect($data['subject_ids'])->map(fn ($id) => (int) $id)->values();
        $assignments = $this->assignmentsFor($student)->whereIn('subject_id', $subjectIds)->keyBy('subject_id');
        if ($assignments->count() !== $subjectIds->count()) {
            return back()->withErrors(['subject_ids' => 'Every selected subject must be in your enrollment and have an active instructor assignment.'])->withInput();
        }
        $primary = $assignments->get($subjectIds->first());
        $duplicate = DB::transaction(function () use ($r, $excuseRequest, $data, $subjectIds, $assignments, $primary, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $current = ExcuseRequest::whereKey($excuseRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->student_id === $student->id && in_array($current->status, ['submitted', 'returned'], true), 403);
            abort_unless($current->placementMatchesStudent(), 403);
            $duplicate = ExcuseRequest::where('student_id', $student->id)
                ->whereDate('absence_date', $data['absence_date'])
                ->where('status', '!=', 'cancelled')
                ->whereKeyNot($excuseRequest->id)
                ->where(fn ($q) => $q->whereIn('subject_id', $subjectIds)
                    ->orWhereHas('subjects', fn ($s) => $s->whereIn('subjects.id', $subjectIds)))
                ->lockForUpdate()->first();
            if ($duplicate) {
                return true;
            }
            $current->update(collect($data)->except(['subject_ids'])->all() + ['subject_id' => $primary->subject_id, 'facilitator_id' => $primary->faculty_id]);
            $current->subjects()->sync($subjectIds->mapWithKeys(fn ($id) => [$id => ['facilitator_id' => $assignments->get($id)->faculty_id]])->all());
            $current->histories()->create(['previous_status' => $current->status, 'new_status' => $current->status, 'action_by' => $r->user()->id, 'remarks' => 'Request details updated by student.']);

            return false;
        });
        if ($duplicate) {
            return back()->withErrors(['absence_date' => 'Another request already exists for one or more selected subjects on this date.'])->withInput();
        }

        return redirect()->route('requests.show', $excuseRequest)->with('success', 'Request details updated successfully.');
    }

    public function submit(ExcuseRequest $excuseRequest, RequestWorkflowService $flow)
    {
        $student = auth()->user()->student;
        abort_unless(auth()->user()->role === 'student' && $student && $excuseRequest->student_id === $student->id, 403);
        $flow->transition($excuseRequest, 'submitted');

        return back()->with('success', 'Request submitted for review.');
    }

    public function cancel(ExcuseRequest $excuseRequest, RequestWorkflowService $flow)
    {
        $student = auth()->user()->student;
        abort_unless($student && $excuseRequest->student_id === $student->id, 403);
        $flow->transition($excuseRequest, 'cancelled', 'Cancelled by student.');

        return redirect()->route('requests.index')->with('success', 'Your request has been cancelled.');
    }

    public function slip(ExcuseRequest $excuseRequest)
    {
        abort_unless($this->permitted($excuseRequest) && in_array($excuseRequest->status, ['approved', 'acknowledged', 'completed']), 403);
        $verificationUrl = route('verify', $excuseRequest->reference_number);
        $qrCode = (new Builder(writer: new SvgWriter, data: $verificationUrl, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 220, margin: 8))->build()->getDataUri();

        return view('requests.slip', ['item' => $excuseRequest->load(['student.user', 'student.course', 'student.section', 'subject', 'facilitator.user', 'subjects', 'facilitators.user', 'reasonCategory']), 'verificationUrl' => $verificationUrl, 'qrCode' => $qrCode, 'programHeadName' => $excuseRequest->slip_issuer_name ?: SystemSetting::valueFor('program_head_name', 'PRINCESS LEA ANN D. CALINA, MSIT')]);
    }

    public function destroy(ExcuseRequest $excuseRequest, ExcuseRequestArchiveService $archives)
    {
        $archives->archiveAndRemove($excuseRequest, auth()->user());

        return redirect()->route('requests.index')->with('success', 'Excuse request and its history archived.');
    }

    private function assignmentsFor($student)
    {
        $query = InstructorAssignment::with(['subject', 'faculty.user'])
            ->where('course_id', $student->course_id)
            ->where('is_active', true)
            ->whereNull('retired_at')
            ->where(fn ($assignments) => $assignments
                ->where('section_id', $student->section_id)
                ->orWhereNull('section_id'));
        if ($student->student_type === 'irregular') {
            $subjectIds = $student->subjects()->pluck('subjects.id');
            $query->whereIn('subject_id', $subjectIds);
        } else {
            $query->where('year_level', $student->year_level);
        }

        return $query->get()
            ->groupBy('subject_id')
            ->map(function ($assignments) use ($student) {
                $blockAssignments = $assignments->where('section_id', $student->section_id);
                $candidates = $blockAssignments->isNotEmpty()
                    ? $blockAssignments
                    : $assignments->whereNull('section_id');

                return $candidates->count() === 1 ? $candidates->first() : null;
            })
            ->filter()
            ->sortBy(fn ($assignment) => sprintf('%03d-%s', $assignment->subject->year_level ?? 999, $assignment->subject->code))
            ->values();
    }
}
