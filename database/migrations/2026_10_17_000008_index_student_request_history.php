<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->index(['student_id', 'created_at', 'id'], 'requests_student_history_index');
        });
    }

    public function down(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->dropIndex('requests_student_history_index');
        });
    }
};
