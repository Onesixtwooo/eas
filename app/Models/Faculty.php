<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faculty extends Model
{
    protected $table = 'faculty';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault(function (User $user, Faculty $faculty) {
            $user->name = $faculty->name ?: 'Unnamed instructor';
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->user?->name ?: 'Unnamed instructor';
    }
}
