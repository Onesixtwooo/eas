<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAbsenceReport extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $report) {
            $student = $report->student()->with(['section', 'course'])->firstOrFail();
            $report->reported_year_level ??= $student->year_level;
            $report->reported_section_id ??= $student->section_id;
            $report->reported_section_name ??= $student->section?->name;
            $report->reported_course_code ??= $student->course?->code;
        });
    }

    protected $casts = [
        'report_date' => 'date',
        'reported_year_level' => 'integer',
        'reported_section_id' => 'integer',
        'adviser_commented_at' => 'datetime',
        'adviser_action_at' => 'datetime',
    ];

    public function assignment() { return $this->belongsTo(InstructorAssignment::class, 'instructor_assignment_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function adviserCommenter() { return $this->belongsTo(User::class, 'adviser_commented_by'); }
    public function adviserActionActor() { return $this->belongsTo(User::class, 'adviser_action_by'); }
}
