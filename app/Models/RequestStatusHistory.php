<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class RequestStatusHistory extends Model {protected $guarded=[];public function actor(){return $this->belongsTo(User::class,'action_by');}}
