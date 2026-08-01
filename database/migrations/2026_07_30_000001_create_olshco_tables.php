<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('courses', function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('name');$t->boolean('is_active')->default(true);$t->timestamps();});
        Schema::create('academic_years', function(Blueprint $t){$t->id();$t->string('name')->unique();$t->boolean('is_current')->default(false);$t->timestamps();});
        Schema::create('semesters', function(Blueprint $t){$t->id();$t->string('name');$t->boolean('is_current')->default(false);$t->timestamps();});
        Schema::create('sections', function(Blueprint $t){$t->id();$t->foreignId('course_id')->constrained()->cascadeOnDelete();$t->string('name');$t->unsignedTinyInteger('year_level');$t->boolean('is_active')->default(true);$t->timestamps();});
        Schema::create('students', function(Blueprint $t){$t->id();$t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();$t->string('student_number')->unique();$t->foreignId('course_id')->constrained();$t->foreignId('section_id')->constrained();$t->unsignedTinyInteger('year_level');$t->timestamps();});
        Schema::create('faculty', function(Blueprint $t){$t->id();$t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();$t->string('employee_number')->nullable()->unique();$t->string('designation')->default('Course Facilitator');$t->timestamps();});
        Schema::create('subjects', function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('name');$t->foreignId('course_id')->constrained();$t->foreignId('facilitator_id')->nullable()->constrained('faculty')->nullOnDelete();$t->boolean('is_active')->default(true);$t->timestamps();});
        Schema::create('reason_categories', function(Blueprint $t){$t->id();$t->string('name')->unique();$t->boolean('is_active')->default(true);$t->timestamps();});
        Schema::create('excuse_requests', function (Blueprint $t) {
            $t->id();$t->string('reference_number')->nullable()->unique();$t->foreignId('student_id')->constrained()->cascadeOnDelete();$t->foreignId('subject_id')->constrained();$t->foreignId('facilitator_id')->constrained('faculty');$t->foreignId('academic_year_id')->constrained();$t->foreignId('semester_id')->constrained();$t->date('absence_date')->index();$t->time('start_time')->nullable();$t->time('end_time')->nullable();$t->foreignId('reason_category_id')->constrained();$t->text('explanation');$t->string('guardian_name')->nullable();$t->string('guardian_contact')->nullable();$t->string('status')->default('draft')->index();$t->text('official_remarks')->nullable();$t->timestamp('submitted_at')->nullable();$t->timestamp('reviewed_at')->nullable();$t->timestamp('approved_at')->nullable();$t->timestamp('rejected_at')->nullable();$t->timestamp('acknowledged_at')->nullable();$t->timestamp('completed_at')->nullable();$t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();
        });
        Schema::create('supporting_documents', function(Blueprint $t){$t->id();$t->foreignId('excuse_request_id')->constrained()->cascadeOnDelete();$t->string('disk')->default('local');$t->string('path');$t->string('original_name');$t->string('mime_type');$t->unsignedBigInteger('size');$t->timestamps();});
        Schema::create('request_status_histories', function(Blueprint $t){$t->id();$t->foreignId('excuse_request_id')->constrained()->cascadeOnDelete();$t->string('previous_status')->nullable();$t->string('new_status');$t->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();$t->text('remarks')->nullable();$t->timestamps();});
        Schema::create('program_head_reviews', function(Blueprint $t){$t->id();$t->foreignId('excuse_request_id')->constrained()->cascadeOnDelete();$t->foreignId('reviewer_id')->constrained('users');$t->string('decision');$t->text('remarks')->nullable();$t->timestamps();});
        Schema::create('instructor_acknowledgments', function(Blueprint $t){$t->id();$t->foreignId('excuse_request_id')->unique()->constrained()->cascadeOnDelete();$t->foreignId('faculty_id')->constrained('faculty');$t->text('remarks')->nullable();$t->boolean('admitted')->default(true);$t->boolean('allow_missed_activities')->default(true);$t->timestamps();});
        Schema::create('system_settings', function(Blueprint $t){$t->id();$t->string('key')->unique();$t->text('value')->nullable();$t->timestamps();});
    }
    public function down(): void {foreach(['system_settings','instructor_acknowledgments','program_head_reviews','request_status_histories','supporting_documents','excuse_requests','reason_categories','subjects','faculty','students','sections','semesters','academic_years','courses'] as $table)Schema::dropIfExists($table);}
};
