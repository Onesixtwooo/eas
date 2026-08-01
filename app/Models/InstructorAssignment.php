<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorAssignment extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function faculty() { return $this->belongsTo(Faculty::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
}
