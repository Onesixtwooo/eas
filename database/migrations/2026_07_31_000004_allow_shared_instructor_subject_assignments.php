<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('instructor_assignments'))->pluck('name');

        if ($indexes->contains('instructor_assignments_course_id_subject_id_year_level_unique')) {
            Schema::table('instructor_assignments', fn (Blueprint $table) => $table->dropUnique('instructor_assignments_course_id_subject_id_year_level_unique'));
        }

        if (! $indexes->contains('ia_faculty_course_subject_year_unique')) {
            Schema::table('instructor_assignments', fn (Blueprint $table) => $table->unique(['faculty_id', 'course_id', 'subject_id', 'year_level'], 'ia_faculty_course_subject_year_unique'));
        }
    }

    public function down(): void
    {
        Schema::table('instructor_assignments', function (Blueprint $table) {
            $table->dropUnique('ia_faculty_course_subject_year_unique');
            $table->unique(['course_id', 'subject_id', 'year_level']);
        });
    }
};
