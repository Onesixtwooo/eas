<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->string('slip_remark', 30)->nullable()->after('official_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->dropColumn('slip_remark');
        });
    }
};
