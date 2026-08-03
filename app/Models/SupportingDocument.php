<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class SupportingDocument extends Model {protected $guarded=[];public function excuseRequest(){return $this->belongsTo(ExcuseRequest::class);}}
