<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slip_amendments', function (Blueprint $table) {
            $table->index(['excuse_request_id', 'id'], 'slip_amendments_latest_index');
        });
    }

    public function down(): void
    {
        Schema::table('slip_amendments', function (Blueprint $table) {
            $table->dropIndex('slip_amendments_latest_index');
        });
    }
};
