<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcuseRequestArchive extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['records' => 'array', 'files' => 'array'];
    }
}
