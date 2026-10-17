<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daily_absence_reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('reported_year_level')->nullable();
            $table->unsignedBigInteger('reported_section_id')->nullable();
            $table->string('reported_section_name')->nullable();
            $table->string('reported_course_code')->nullable();
            $table->index(['reported_year_level', 'report_date', 'reported_section_id'], 'attendance_advisory_history_index');
        });

        // Placement changes made before this migration cannot be reconstructed.
        // Use the best placement still available for existing reports.
        DB::table('daily_absence_reports')->select('id', 'student_id')->orderBy('id')->chunkById(500, function ($reports) {
            $students = DB::table('students')
                ->leftJoin('sections', 'sections.id', '=', 'students.section_id')
                ->leftJoin('courses', 'courses.id', '=', 'students.course_id')
                ->whereIn('students.id', $reports->pluck('student_id'))
                ->select('students.id', 'students.year_level', 'students.section_id', 'sections.name as section_name', 'courses.code as course_code')
                ->get()->keyBy('id');

            foreach ($reports as $report) {
                $student = $students->get($report->student_id);
                if ($student) {
                    DB::table('daily_absence_reports')->where('id', $report->id)->update([
                        'reported_year_level' => $student->year_level,
                        'reported_section_id' => $student->section_id,
                        'reported_section_name' => $student->section_name,
                        'reported_course_code' => $student->course_code,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_absence_reports', function (Blueprint $table) {
            $table->dropIndex('attendance_advisory_history_index');
            $table->dropColumn(['reported_year_level', 'reported_section_id', 'reported_section_name', 'reported_course_code']);
        });
    }
};
