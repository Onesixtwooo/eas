<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daily_absence_reports', function (Blueprint $table) {
            $table->string('adviser_action')->nullable()->after('adviser_commented_at')->index();
            $table->foreignId('adviser_action_by')->nullable()->after('adviser_action')->constrained('users')->nullOnDelete();
            $table->timestamp('adviser_action_at')->nullable()->after('adviser_action_by');
        });
    }

    public function down(): void
    {
        Schema::table('daily_absence_reports', function (Blueprint $table) {
            $table->dropIndex(['adviser_action']);
            $table->dropConstrainedForeignId('adviser_action_by');
            $table->dropColumn(['adviser_action', 'adviser_action_at']);
        });
    }
};
