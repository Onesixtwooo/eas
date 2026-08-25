<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daily_absence_reports', function (Blueprint $table) {
            $table->text('adviser_comment')->nullable()->after('comment');
            $table->foreignId('adviser_commented_by')->nullable()->after('adviser_comment')->constrained('users')->nullOnDelete();
            $table->timestamp('adviser_commented_at')->nullable()->after('adviser_commented_by');
        });
    }

    public function down(): void
    {
        Schema::table('daily_absence_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adviser_commented_by');
            $table->dropColumn(['adviser_comment', 'adviser_commented_at']);
        });
    }
};
