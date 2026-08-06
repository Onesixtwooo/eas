<?php

namespace App\Services;

use App\Models\ExcuseRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestWorkflowService
{
    private array $flows = [
        'draft' => ['submitted'],
        'returned' => ['submitted'],
        'submitted' => ['under_review', 'cancelled'],
        'under_review' => ['approved', 'returned', 'rejected'],
        'rejected' => ['under_review', 'approved', 'returned'],
        'approved' => ['approved', 'under_review', 'returned', 'rejected', 'acknowledged'],
        'acknowledged' => ['completed'],
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
        $parts = collect(preg_split('/\s+/u', trim($request->student->user->name)))
            ->filter()
            ->values();
        $firstInitial = Str::upper(Str::substr($parts->first() ?? 'X', 0, 1));
        $surnameInitial = Str::upper(Str::substr($parts->last() ?? 'X', 0, 1));

        return sprintf(
            'EAS-%s-%s%s-%04d',
            now()->format('Y'),
            $surnameInitial,
            $firstInitial,
            $request->id,
        );
    }
}
