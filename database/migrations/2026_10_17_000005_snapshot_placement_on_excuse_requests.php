<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('reported_year_level')->nullable();
            $table->unsignedBigInteger('reported_section_id')->nullable();
            $table->string('reported_section_name')->nullable();
            $table->string('reported_course_code')->nullable();
            $table->index(['reported_year_level', 'status'], 'request_reported_year_status_index');
        });

        // Placement before this migration was not recorded on requests.
        // Backfill from the best current student placement available.
        DB::table('excuse_requests')->select('id', 'student_id')->orderBy('id')->chunkById(500, function ($requests) {
            $students = DB::table('students')
                ->leftJoin('sections', 'sections.id', '=', 'students.section_id')
                ->leftJoin('courses', 'courses.id', '=', 'students.course_id')
                ->whereIn('students.id', $requests->pluck('student_id'))
                ->select('students.id', 'students.year_level', 'students.section_id', 'sections.name as section_name', 'courses.code as course_code')
                ->get()->keyBy('id');

            foreach ($requests as $request) {
                if ($student = $students->get($request->student_id)) {
                    DB::table('excuse_requests')->where('id', $request->id)->update([
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
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->dropIndex('request_reported_year_status_index');
            $table->dropColumn(['reported_year_level', 'reported_section_id', 'reported_section_name', 'reported_course_code']);
        });
    }
};
