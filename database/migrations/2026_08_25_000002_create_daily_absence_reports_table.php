<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_absence_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('report_date')->index();
            $table->timestamps();

            $table->unique(
                ['instructor_assignment_id', 'student_id', 'report_date'],
                'daily_absence_assignment_student_date_unique'
            );
            $table->index(['student_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_absence_reports');
    }
};
