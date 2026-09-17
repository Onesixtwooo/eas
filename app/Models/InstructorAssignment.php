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
    public function section() { return $this->belongsTo(Section::class); }
    public function dailyAbsenceReports() { return $this->hasMany(DailyAbsenceReport::class); }

    public function getClassKeyAttribute(): string
    {
        $course = $this->course?->code ?? 'course';
        $subject = $this->subject?->code ?? 'subject';
        $section = $this->section?->name ?? 'all';

        return \Illuminate\Support\Str::lower("{$course}-{$subject}-{$section}-y{$this->year_level}");
    }
}
