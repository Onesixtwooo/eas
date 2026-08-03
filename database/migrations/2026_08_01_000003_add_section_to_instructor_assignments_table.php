<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may use the composite unique index for these foreign keys.
        // Give each key a dedicated index before replacing that unique index.
        Schema::table('instructor_assignments', function (Blueprint $table) {
            $table->index('faculty_id', 'ia_faculty_fk_index');
            $table->index('course_id', 'ia_course_fk_index');
            $table->index('subject_id', 'ia_subject_fk_index');
        });

        Schema::table('instructor_assignments', function (Blueprint $table) {
            $table->dropUnique('ia_faculty_course_subject_year_unique');
            $table->foreignId('section_id')->nullable()->after('year_level')->constrained()->cascadeOnDelete();
            $table->unique(['faculty_id', 'course_id', 'subject_id', 'year_level', 'section_id'], 'ia_faculty_course_subject_year_section_unique');
            $table->index(['course_id', 'year_level', 'section_id', 'is_active'], 'ia_student_assignment_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_assignments', function (Blueprint $table) {
            $table->dropIndex('ia_student_assignment_lookup');
            $table->dropUnique('ia_faculty_course_subject_year_section_unique');
            $table->dropConstrainedForeignId('section_id');
            $table->unique(['faculty_id', 'course_id', 'subject_id', 'year_level'], 'ia_faculty_course_subject_year_unique');
        });

        Schema::table('instructor_assignments', function (Blueprint $table) {
            $table->dropIndex('ia_faculty_fk_index');
            $table->dropIndex('ia_course_fk_index');
            $table->dropIndex('ia_subject_fk_index');
        });
    }
};
