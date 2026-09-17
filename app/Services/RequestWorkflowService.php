<?php

namespace App\Services;

use App\Models\ExcuseRequest;
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
        if (! in_array($to, $this->flows[$request->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'This status transition is not allowed.']);
        }

        return DB::transaction(function () use ($request, $to, $remarks, $slipRemark) {
            $from = $request->status;
            $dates = [
                'submitted' => 'submitted_at',
                'under_review' => 'reviewed_at',
                'approved' => 'approved_at',
                'rejected' => 'rejected_at',
                'acknowledged' => 'acknowledged_at',
                'completed' => 'completed_at',
            ];
            $data = ['status' => $to];

            if (isset($dates[$to])) {
                $data[$dates[$to]] = now();
            }

            if ($to === 'approved') {
                $data['reference_number'] = $request->reference_number ?? $this->referenceNumber($request);
                $data['reviewed_by'] = auth()->id();
                $data['slip_remark'] = $slipRemark ?? 'EXCUSED';
            }

            if (in_array($to, ['approved', 'returned', 'rejected'], true)) {
                $data['official_remarks'] = $remarks;
            }

            $request->update($data);
            $request->histories()->create([
                'previous_status' => $from,
                'new_status' => $to,
                'action_by' => auth()->id(),
                'remarks' => $remarks,
            ]);

            return $request->refresh();
        });
    }

    private function referenceNumber(ExcuseRequest $request): string
    {
        $year = now()->format('Y');

        do {
            $candidate = sprintf(
                'EAS-%s-%s-%s',
                $year,
                Str::upper(Str::random(4)),
                Str::upper(Str::random(4)),
            );
        } while (ExcuseRequest::where('reference_number', $candidate)->exists());

        return $candidate;
    }
}
