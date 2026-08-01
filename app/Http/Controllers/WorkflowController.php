<?php
namespace App\Http\Controllers;
use App\Models\ExcuseRequest;
use App\Services\RequestWorkflowService;
use Illuminate\Http\Request;
class WorkflowController extends Controller {
 public function review(Request $r,ExcuseRequest $excuseRequest,RequestWorkflowService $flow){abort_unless(in_array(auth()->user()->role,['admin','program_head'],true),403);$data=$r->validate(['decision'=>'required|in:under_review,approved,returned,rejected','remarks'=>'nullable|required_if:decision,returned,rejected|string|max:2000','slip_remark'=>'nullable|required_if:decision,approved|in:EXCUSED,UNEXCUSED,CONDITIONAL']);$flow->transition($excuseRequest,$data['decision'],$data['remarks']??null,$data['slip_remark']??null);return back()->with('success','Request status updated.');}
 public function acknowledge(Request $r,ExcuseRequest $excuseRequest,RequestWorkflowService $flow){abort_unless(auth()->user()->role==='faculty'&&$excuseRequest->facilitator_id===auth()->user()->faculty->id,403);$r->validate(['remarks'=>'nullable|string|max:1000']);$flow->transition($excuseRequest,'acknowledged',$r->remarks);return back()->with('success','Slip acknowledged.');}
 public function complete(ExcuseRequest $excuseRequest,RequestWorkflowService $flow){abort_unless(auth()->user()->role==='faculty'&&$excuseRequest->facilitator_id===auth()->user()->faculty->id,403);$flow->transition($excuseRequest,'completed');return back()->with('success','Request completed.');}
}
