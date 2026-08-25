<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAbsenceReport extends Model
{
    protected $guarded = [];
    protected $casts = [
        'report_date' => 'date',
        'adviser_commented_at' => 'datetime',
        'adviser_action_at' => 'datetime',
    ];

    public function assignment() { return $this->belongsTo(InstructorAssignment::class, 'instructor_assignment_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function adviserCommenter() { return $this->belongsTo(User::class, 'adviser_commented_by'); }
    public function adviserActionActor() { return $this->belongsTo(User::class, 'adviser_action_by'); }
}
