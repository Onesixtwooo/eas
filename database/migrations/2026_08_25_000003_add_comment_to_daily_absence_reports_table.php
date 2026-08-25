<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daily_absence_reports', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('report_date');
        });
    }

    public function down(): void
    {
        Schema::table('daily_absence_reports', fn (Blueprint $table) => $table->dropColumn('comment'));
    }
};
