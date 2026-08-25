<?php

namespace App\Http\Controllers;

use App\Models\ExcuseRequest;
use App\Services\RequestWorkflowService;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function review(Request $r, ExcuseRequest $excuseRequest, RequestWorkflowService $flow)
    {
        abort_unless(in_array(auth()->user()->role, ['admin', 'program_head'], true), 403);
        $data = $r->validate(['decision' => 'required|in:under_review,approved,returned,rejected', 'remarks' => 'nullable|required_if:decision,returned,rejected|string|max:2000', 'slip_remark' => 'nullable|required_if:decision,approved|in:EXCUSED,UNEXCUSED,CONDITIONAL']);
        $flow->transition($excuseRequest, $data['decision'], $data['remarks'] ?? null, $data['slip_remark'] ?? null);

        return back()->with('success', 'Request status updated.');
    }

}
