<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_status_histories', function (Blueprint $table) {
            $table->dropForeign(['action_by']);
            $table->unsignedBigInteger('action_by')->nullable()->change();
            $table->foreign('action_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('request_status_histories', function (Blueprint $table) {
            $table->dropForeign(['action_by']);
            $table->foreign('action_by')->references('id')->on('users');
        });
    }
};
