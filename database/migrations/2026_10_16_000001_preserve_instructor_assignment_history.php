<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('instructor_assignments', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('instructor_assignments', fn (Blueprint $table) => $table->dropColumn('retired_at'));
    }
};
