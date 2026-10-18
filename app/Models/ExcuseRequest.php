<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcuseRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['absence_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'acknowledged_at' => 'datetime', 'completed_at' => 'datetime'];

    public function placementLabel(): string
    {
        $course = $this->reported_course_code ?? $this->student?->course?->code;
        $year = $this->reported_year_level ?? $this->student?->year_level;
        $section = $this->reported_section_name ?? $this->student?->section?->name;

        return trim($course.' '.$year.($section ? '-'.$section : ''));
    }

    public function placementMatchesStudent(): bool
    {
        return ($this->reported_year_level === null || (int) $this->reported_year_level === (int) $this->student->year_level)
            && ($this->reported_section_id === null || (int) $this->reported_section_id === (int) $this->student->section_id);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function facilitator()
    {
        return $this->belongsTo(Faculty::class, 'facilitator_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'excuse_request_subject')->withPivot('facilitator_id')->withTimestamps();
    }

    public function facilitators()
    {
        return $this->belongsToMany(Faculty::class, 'excuse_request_subject', 'excuse_request_id', 'facilitator_id')->withPivot('subject_id')->withTimestamps();
    }

    public function reasonCategory()
    {
        return $this->belongsTo(ReasonCategory::class);
    }

    public function histories()
    {
        return $this->hasMany(RequestStatusHistory::class)->latest();
    }

    public function documents()
    {
        return $this->hasMany(SupportingDocument::class);
    }

    public function slipIssuance()
    {
        return $this->hasOne(SlipIssuance::class);
    }

    public function slipAmendments()
    {
        return $this->hasMany(SlipAmendment::class)->orderByDesc('id');
    }
}
