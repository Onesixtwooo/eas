<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->string('attendance_type', 10)->default('absent')->after('absence_date');
        });
    }

    public function down(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->dropColumn('attendance_type');
        });
    }
};
