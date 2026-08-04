<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageConversationState extends Model
{
    protected $fillable = ['user_id', 'student_id', 'archived_at', 'deleted_before_message_id'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }
}
