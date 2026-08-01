<?php
namespace App\Services;
use App\Models\ExcuseRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class RequestWorkflowService {
    private array $flows=['draft'=>['submitted'],'returned'=>['submitted'],'submitted'=>['under_review'],'under_review'=>['approved','returned','rejected'],'rejected'=>['under_review','approved','returned'],'approved'=>['approved','under_review','returned','rejected','acknowledged'],'acknowledged'=>['completed']];
    public function transition(ExcuseRequest $request,string $to,?string $remarks=null,?string $slipRemark=null): ExcuseRequest {
        if(!in_array($to,$this->flows[$request->status]??[],true))throw ValidationException::withMessages(['status'=>'This status transition is not allowed.']);
        return DB::transaction(function()use($request,$to,$remarks,$slipRemark){$from=$request->status;$dates=['submitted'=>'submitted_at','under_review'=>'reviewed_at','approved'=>'approved_at','rejected'=>'rejected_at','acknowledged'=>'acknowledged_at','completed'=>'completed_at'];$data=['status'=>$to];if(isset($dates[$to]))$data[$dates[$to]]=now();if($to==='approved'){$data['reference_number']='EAS-'.now()->format('Y').'-'.str_pad((string)$request->id,4,'0',STR_PAD_LEFT);$data['reviewed_by']=auth()->id();$data['slip_remark']=$slipRemark??'EXCUSED';}if(in_array($to,['approved','returned','rejected']))$data['official_remarks']=$remarks;$request->update($data);$request->histories()->create(['previous_status'=>$from,'new_status'=>$to,'action_by'=>auth()->id(),'remarks'=>$remarks]);return $request->refresh();});
    }
}
