<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->index('submitted_at', 'requests_submitted_at_index');
            $table->index('approved_at', 'requests_approved_at_index');
            $table->index('completed_at', 'requests_completed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->dropIndex('requests_submitted_at_index');
            $table->dropIndex('requests_approved_at_index');
            $table->dropIndex('requests_completed_at_index');
        });
    }
};
