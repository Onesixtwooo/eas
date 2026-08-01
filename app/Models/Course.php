<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $guarded = [];

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
}
