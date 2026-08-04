<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['student_id', 'sender_id', 'body', 'read_at', 'edited_at', 'unsent_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'edited_at' => 'datetime', 'unsent_at' => 'datetime'];
    }

    public function student() { return $this->belongsTo(Student::class); }
    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }
}
