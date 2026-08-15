<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExcuseRequest;
use App\Models\AcademicYear;
use App\Models\ExcuseRequest;
use App\Models\InstructorAssignment;
use App\Models\ReasonCategory;
use App\Models\Semester;
use App\Models\SupportingDocument;
use App\Models\SystemSetting;
use App\Services\RequestWorkflowService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExcuseRequestController extends Controller
{
    private function permitted(ExcuseRequest $e): bool
    {
        $u = auth()->user();

        return $u->role === 'admin' || $u->role === 'program_head' || ($u->role === 'student' && $e->student_id === $u->student->id) || ($u->role === 'faculty' && ($e->facilitator_id === $u->faculty->id || $e->facilitators()->whereKey($u->faculty->id)->exists()));
    }

    public function index(Request $r)
    {
        $q = ExcuseRequest::with(['student.user', 'subject', 'facilitator.user', 'subjects', 'facilitators.user']);
        $u = auth()->user();
        if ($u->role === 'student') {
            $q->where('student_id', $u->student->id);
        }if ($u->role === 'faculty') {
            $q->where(fn ($x) => $x->where('facilitator_id', $u->faculty->id)->orWhereHas('facilitators', fn ($y) => $y->whereKey($u->faculty->id)));
        }if ($r->filled('status')) {
            $q->where('status', $r->status);
        }if ($r->filled('search')) {
            $search = trim($r->search);
            $q->where(fn ($x) => $x->where('reference_number', 'like', '%'.$search.'%')->orWhereHas('subjects', fn ($y) => $y->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'))->orWhereHas('subject', fn ($y) => $y->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'))->when($u->role !== 'student', fn ($y) => $y->orWhereHas('student.user', fn ($z) => $z->where('name', 'like', '%'.$search.'%'))));
        }$requests = $q->latest()->paginate(15)->withQueryString();
        $programHeadName = SystemSetting::valueFor('program_head_name', 'PRINCESS LEA ANN D. CALINA, MSIT');

        return view('requests.index', compact('requests', 'programHeadName'));
    }

    public function create()
    {
        $student = auth()->user()->student;
        $assignments = $this->assignmentsFor($student);

        return view('requests.form', ['requestItem' => new ExcuseRequest, 'assignments' => $assignments, 'reasons' => ReasonCategory::where('is_active', true)->get()]);
    }

    public function store(StoreExcuseRequest $r, RequestWorkflowService $flow)
    {
        $data = $r->validated();
        $subjectIds = collect($data['subject_ids'])->map(fn ($id) => (int) $id)->values();
        unset($data['subject_ids'],$data['document'],$data['declaration'],$data['intent']);
        $student = $r->user()->student;
        $assignments = $this->assignmentsFor($student)->whereIn('subject_id', $subjectIds)->keyBy('subject_id');
        if ($assignments->count() !== $subjectIds->count()) {
            return back()->withErrors(['subject_ids' => 'Every selected subject must be in your current enrollment and have an active instructor assignment.'])->withInput();
        }$existing = ExcuseRequest::where('student_id', $student->id)->whereDate('absence_date', $data['absence_date'])->where('status', '!=', 'cancelled')->where(fn ($q) => $q->whereIn('subject_id', $subjectIds)->orWhereHas('subjects', fn ($s) => $s->whereIn('subjects.id', $subjectIds)))->latest()->first();
        if ($existing) {
            return redirect()->route('requests.show', $existing)->with('error', 'A request already exists for one or more selected subjects on this absence date.');
        }$academicYearId = AcademicYear::where('is_current', true)->value('id');
        $semesterId = Semester::where('is_current', true)->value('id');
        if (! $academicYearId || ! $semesterId) {
            throw ValidationException::withMessages(['academic_period' => 'The current academic year and semester have not been configured. Please contact the administrator.']);
        }$primary = $assignments->get($subjectIds->first());
        $data += ['student_id' => $student->id, 'subject_id' => $primary->subject_id, 'facilitator_id' => $primary->faculty_id, 'academic_year_id' => $academicYearId, 'semester_id' => $semesterId, 'status' => 'draft'];
        $item = DB::transaction(function () use ($r, $data, $subjectIds, $assignments) {
            $item = ExcuseRequest::create($data);
            $item->subjects()->attach($subjectIds->mapWithKeys(fn ($id) => [$id => ['facilitator_id' => $assignments->get($id)->faculty_id]])->all());
            $item->histories()->create(['new_status' => 'draft', 'action_by' => $r->user()->id, 'remarks' => 'Request created for '.$subjectIds->count().' subject(s)']);

            return $item;
        });
        if ($r->hasFile('document')) {
            $f = $r->file('document');
            $item->documents()->create(['path' => $f->store('supporting-documents'), 'original_name' => $f->getClientOriginalName(), 'mime_type' => $f->getMimeType(), 'size' => $f->getSize()]);
        }if ($r->intent === 'submit') {
            $flow->transition($item, 'submitted');
        }

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
        $excuseRequest->load(['documents', 'subjects']);

        return view('requests.edit', ['item' => $excuseRequest, 'assignments' => $this->assignmentsFor($student), 'reasons' => ReasonCategory::where('is_active', true)->get()]);
    }

    public function document(SupportingDocument $document)
    {
        $document->load('excuseRequest.student.user');
        abort_unless($this->permitted($document->excuseRequest), 403);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->response($document->path, $document->original_name, ['Content-Type' => $document->mime_type, 'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox", 'X-Content-Type-Options' => 'nosniff']);
    }

    public function updateAttachment(Request $r, ExcuseRequest $excuseRequest)
    {
        $student = $r->user()->student;
        abort_unless($student && $excuseRequest->student_id === $student->id, 403);
        abort_unless(in_array($excuseRequest->status, ['draft', 'returned', 'submitted'], true), 403);
        $data = $r->validate(['document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $file = $data['document'];
        $path = $file->store('supporting-documents', 'local');
        if (! $path) {
            throw ValidationException::withMessages(['document' => 'The attachment could not be stored. Please try again.']);
        }$oldDocuments = $excuseRequest->documents()->get();
        $excuseRequest->documents()->create(['disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
        foreach ($oldDocuments as $oldDocument) {
            Storage::disk($oldDocument->disk)->delete($oldDocument->path);
            $oldDocument->delete();
        }$excuseRequest->histories()->create(['previous_status' => $excuseRequest->status, 'new_status' => $excuseRequest->status, 'action_by' => $r->user()->id, 'remarks' => 'Supporting attachment replaced by student.']);

        return back()->with('success', 'Supporting attachment changed successfully.');
    }

    public function update(Request $r, ExcuseRequest $excuseRequest)
    {
        $student = $r->user()->student;
        abort_unless($student && $excuseRequest->student_id === $student->id, 403);
        abort_unless(in_array($excuseRequest->status, ['submitted', 'returned'], true), 403);
        if (! $r->has('subject_ids') && $r->filled('subject_id')) {
            $r->merge(['subject_ids' => [$r->input('subject_id')]]);
        }
        $data = $r->validate(['absence_date' => 'required|date|before_or_equal:today', 'subject_ids' => 'required|array|min:1', 'subject_ids.*' => 'integer|distinct|exists:subjects,id', 'reason_category_id' => 'required|exists:reason_categories,id', 'explanation' => 'required|string|min:20|max:3000', 'start_time' => 'nullable|date_format:H:i', 'end_time' => 'nullable|date_format:H:i|after:start_time', 'guardian_name' => 'nullable|string|max:255', 'guardian_contact' => 'nullable|string|max:30', 'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $subjectIds = collect($data['subject_ids'])->map(fn ($id) => (int) $id)->values();
        $assignments = $this->assignmentsFor($student)->whereIn('subject_id', $subjectIds)->keyBy('subject_id');
        if ($assignments->count() !== $subjectIds->count()) {
            return back()->withErrors(['subject_ids' => 'Every selected subject must be in your enrollment and have an active instructor assignment.'])->withInput();
        }$duplicate = ExcuseRequest::where('student_id', $student->id)->whereDate('absence_date', $data['absence_date'])->where('status', '!=', 'cancelled')->whereKeyNot($excuseRequest->id)->where(fn ($q) => $q->whereIn('subject_id', $subjectIds)->orWhereHas('subjects', fn ($s) => $s->whereIn('subjects.id', $subjectIds)))->first();
        if ($duplicate) {
            return back()->withErrors(['absence_date' => 'Another request already exists for one or more selected subjects on this date.'])->withInput();
        }$oldDocuments = $excuseRequest->documents()->get();
        $primary = $assignments->get($subjectIds->first());
        DB::transaction(function () use ($r, $excuseRequest, $data, $subjectIds, $assignments, $primary) {
            $excuseRequest->update(collect($data)->except(['document', 'subject_ids'])->all() + ['subject_id' => $primary->subject_id, 'facilitator_id' => $primary->faculty_id]);
            $excuseRequest->subjects()->sync($subjectIds->mapWithKeys(fn ($id) => [$id => ['facilitator_id' => $assignments->get($id)->faculty_id]])->all());
            if ($r->hasFile('document')) {
                $file = $r->file('document');
                $path = $file->store('supporting-documents', 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['document' => 'The attachment could not be stored. Please try again.']);
                }$excuseRequest->documents()->delete();
                $excuseRequest->documents()->create(['disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
            }$excuseRequest->histories()->create(['previous_status' => $excuseRequest->status, 'new_status' => $excuseRequest->status, 'action_by' => $r->user()->id, 'remarks' => $r->hasFile('document') ? 'Request details and supporting attachment updated by student.' : 'Request details updated by student.']);
        });
        if ($r->hasFile('document')) {
            foreach ($oldDocuments as $document) {
                Storage::disk($document->disk)->delete($document->path);
            }
        }

        return redirect()->route('requests.show', $excuseRequest)->with('success', 'Request details updated successfully.');
    }

    public function submit(ExcuseRequest $excuseRequest, RequestWorkflowService $flow)
    {
        abort_unless(auth()->user()->role === 'student' && $excuseRequest->student_id === auth()->user()->student->id, 403);
        $flow->transition($excuseRequest, 'submitted');

        return back()->with('success', 'Request submitted for review.');
    }

    public function cancel(ExcuseRequest $excuseRequest, RequestWorkflowService $flow)
    {
        abort_unless($excuseRequest->student_id === auth()->user()->student->id, 403);
        $flow->transition($excuseRequest, 'cancelled', 'Cancelled by student.');

        return redirect()->route('requests.index')->with('success', 'Your request has been cancelled.');
    }

    public function slip(ExcuseRequest $excuseRequest)
    {
        abort_unless($this->permitted($excuseRequest) && in_array($excuseRequest->status, ['approved', 'acknowledged', 'completed']), 403);
        $verificationUrl = route('verify', $excuseRequest->reference_number);
        $qrCode = (new Builder(writer: new SvgWriter, data: $verificationUrl, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 220, margin: 8))->build()->getDataUri();

        return view('requests.slip', ['item' => $excuseRequest->load(['student.user', 'student.course', 'student.section', 'subject', 'facilitator.user', 'subjects', 'facilitators.user', 'reasonCategory']), 'verificationUrl' => $verificationUrl, 'qrCode' => $qrCode, 'programHeadName' => SystemSetting::valueFor('program_head_name', 'PRINCESS LEA ANN D. CALINA, MSIT')]);
    }

    public function destroy(ExcuseRequest $excuseRequest)
    {
        $documents = $excuseRequest->documents()->get(['disk', 'path']);
        DB::transaction(fn () => $excuseRequest->delete());
        foreach ($documents as $document) {
            Storage::disk($document->disk)->delete($document->path);
        }

        return redirect()->route('requests.index')->with('success', 'Excuse request deleted permanently.');
    }

    private function assignmentsFor($student)
    {
        $query = InstructorAssignment::with(['subject', 'faculty.user'])->where('course_id', $student->course_id)->where('is_active', true);
        if ($student->student_type === 'irregular') {
            $subjectIds = $student->subjects()->pluck('subjects.id');
            $query->whereIn('subject_id', $subjectIds)->orderByRaw('case when section_id = ? then 0 when section_id is null then 1 else 2 end', [$student->section_id]);
        } else {
            $query->where('year_level', $student->year_level)->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $student->section_id))->orderByDesc('section_id');
        }

        return $query->get()->unique('subject_id')->sortBy(fn ($assignment) => sprintf('%03d-%s', $assignment->subject->year_level ?? 999, $assignment->subject->code))->values();
    }
}
