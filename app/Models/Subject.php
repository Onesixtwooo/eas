<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Subject extends Model {protected $guarded=[];public function course(){return $this->belongsTo(Course::class);}public function facilitator(){return $this->belongsTo(Faculty::class,'facilitator_id');}}
