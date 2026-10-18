<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlipIssuance extends Model
{
    protected $guarded = [];

    protected $casts = ['snapshot' => 'array'];
}
