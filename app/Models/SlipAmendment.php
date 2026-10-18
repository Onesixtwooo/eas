<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlipAmendment extends Model
{
    protected $guarded = [];

    public function actor()
    {
        return $this->belongsTo(User::class, 'action_by');
    }
}
