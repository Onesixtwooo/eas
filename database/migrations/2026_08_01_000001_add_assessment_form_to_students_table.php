<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('assessment_form_path')->nullable()->after('address');
            $table->string('assessment_form_name')->nullable()->after('assessment_form_path');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['assessment_form_path', 'assessment_form_name']);
        });
    }
};
