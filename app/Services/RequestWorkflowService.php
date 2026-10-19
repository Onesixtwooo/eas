<?php

namespace App\Services;

use App\Models\ExcuseRequest;
use App\Models\Faculty;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestWorkflowService
{
    private array $flows = [
        'draft' => ['submitted', 'cancelled'],
        'returned' => ['submitted', 'cancelled'],
        'submitted' => ['under_review', 'cancelled'],
        'under_review' => ['approved', 'returned', 'rejected'],
        'rejected' => ['under_review', 'approved', 'returned'],
        'approved' => ['approved', 'under_review', 'returned', 'rejected'],
    ];

    public function transition(ExcuseRequest $request, string $to, ?string $remarks = null, ?string $slipRemark = null): ExcuseRequest
    {
        return DB::transaction(function () use ($request, $to, $remarks, $slipRemark) {
            $request = ExcuseRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! in_array($to, $this->flows[$request->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This status transition is not allowed.']);
            }
            $from = $request->status;
            $wasIssued = $request->approved_at !== null || $request->reference_number !== null;
            if ($wasIssued && ! ($from === 'approved' && in_array($to, ['approved', 'rejected'], true))) {
                throw ValidationException::withMessages(['status' => 'An issued request cannot be reopened or returned for editing. Record an amendment or reject it so the student can submit a new request.']);
            }
            $dates = [
                'submitted' => 'submitted_at',
                'under_review' => 'reviewed_at',
                'approved' => 'approved_at',
                'rejected' => 'rejected_at',
                'acknowledged' => 'acknowledged_at',
                'completed' => 'completed_at',
            ];
            $data = ['status' => $to];

            $alreadyIssued = $to === 'approved' && $wasIssued;
            if ($alreadyIssued && blank($remarks)) {
                throw ValidationException::withMessages(['remarks' => 'Explain the approval amendment. The original slip will remain unchanged.']);
            }
            if (isset($dates[$to]) && ! $alreadyIssued) {
                $data[$dates[$to]] = now();
            }

            if ($to === 'approved' && ! $alreadyIssued) {
                $data['reference_number'] = $request->reference_number ?? $this->referenceNumber($request);
                $data['reviewed_by'] = auth()->id();
                $data['slip_remark'] = $slipRemark ?? 'EXCUSED';
                $data['slip_issuer_name'] = trim(SystemSetting::valueFor('program_head_name', 'PRINCESS LEA ANN D. CALINA, MSIT'));
            }

            if (in_array($to, ['approved', 'returned', 'rejected'], true) && ! $alreadyIssued) {
                $data['official_remarks'] = $remarks;
            }

            if ($alreadyIssued && ! $request->slipIssuance()->exists()) {
                $request->slipIssuance()->create(['snapshot' => $this->slipSnapshot($request, 'reconstructed')]);
            }
            $request->update($data);
            if ($to === 'approved' && ! $alreadyIssued) {
                $request->slipIssuance()->create(['snapshot' => $this->slipSnapshot($request)]);
            } elseif ($alreadyIssued) {
                $request->slipAmendments()->create([
                    'action_by' => auth()->id(),
                    'slip_remark' => $slipRemark,
                    'remarks' => $remarks,
                ]);
            }
            $request->histories()->create([
                'previous_status' => $from,
                'new_status' => $to,
                'action_by' => auth()->id(),
                'remarks' => $alreadyIssued ? 'Approval amendment recorded. Original slip preserved. '.($remarks ?? '') : $remarks,
            ]);

            return $request->refresh();
        });
    }

    public function slipSnapshot(ExcuseRequest $request, string $source = 'captured'): array
    {
        $request->load(['student.user', 'subject', 'facilitator.user', 'subjects', 'reasonCategory']);
        $subjects = $request->subjects->isNotEmpty() ? $request->subjects : collect([$request->subject]);
        $facultyIds = $request->subjects->isNotEmpty()
            ? $request->subjects->pluck('pivot.facilitator_id')->filter()->unique()
            : collect([$request->facilitator_id]);
        $faculty = Faculty::with('user')->whereIn('id', $facultyIds)->get()->keyBy('id');
        $recipientLines = $request->subjects->isNotEmpty()
            ? $subjects->map(fn ($subject) => $subject->code.' — '.($faculty->get($subject->pivot->facilitator_id)?->display_name ?? 'Instructor'))->all()
            : [$request->subject->code.' — '.$request->facilitator->display_name];

        return [
            'snapshot_source' => $source,
            'reference_number' => $request->reference_number,
            'issued_at' => $request->approved_at?->toIso8601String(),
            'issuer_name' => $request->slip_issuer_name,
            'student_name' => $request->student->user->name,
            'student_number' => $request->student->student_number,
            'reason_name' => $request->reasonCategory->name,
            'absence_date' => $request->absence_date->toDateString(),
            'attendance_type' => $request->attendance_type ?? 'absent',
            'start_time' => $request->start_time,
            'subject_codes' => $subjects->pluck('code')->all(),
            'facilitator_names' => $faculty->pluck('user.name')->filter()->unique()->values()->all(),
            'recipient_lines' => $recipientLines,
            'slip_remark' => $request->slip_remark,
            'official_remarks' => $request->official_remarks,
        ];
    }

    private function referenceNumber(ExcuseRequest $request): string
    {
        do {
            $candidate = (string) Str::uuid();
        } while (ExcuseRequest::where('reference_number', $candidate)->exists());

        return $candidate;
    }
}
