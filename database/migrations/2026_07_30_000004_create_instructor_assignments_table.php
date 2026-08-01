<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('instructor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculty')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('year_level');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['faculty_id', 'course_id', 'subject_id', 'year_level'], 'ia_faculty_course_subject_year_unique');
            $table->index(['course_id', 'year_level', 'is_active']);
        });

        $now = now();
        foreach (\Illuminate\Support\Facades\DB::table('subjects')->whereNotNull('facilitator_id')->get() as $subject) {
            foreach (range(1, 5) as $yearLevel) {
                \Illuminate\Support\Facades\DB::table('instructor_assignments')->insert([
                    'faculty_id' => $subject->facilitator_id,
                    'course_id' => $subject->course_id,
                    'subject_id' => $subject->id,
                    'year_level' => $yearLevel,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_assignments');
    }
};
