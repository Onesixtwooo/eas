<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcuseRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['absence_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'acknowledged_at' => 'datetime', 'completed_at' => 'datetime'];

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
}
