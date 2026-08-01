<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Student extends Model {protected $guarded=[];public function user(){return $this->belongsTo(User::class);}public function course(){return $this->belongsTo(Course::class);}public function section(){return $this->belongsTo(Section::class);}public function requests(){return $this->hasMany(ExcuseRequest::class);}}
