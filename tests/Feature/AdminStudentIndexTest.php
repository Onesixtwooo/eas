<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\AcademicYear;
use App\Models\ExcuseRequest;
use App\Models\Faculty;
use App\Models\ReasonCategory;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStudentIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_filter_only_lists_sections_used_by_saved_students(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $studentUser = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        $course = Course::create([
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
        ]);
        $savedSection = Section::create([
            'course_id' => $course->id,
            'name' => 'A',
            'year_level' => 5,
            'is_active' => true,
        ]);
        $unusedSection = Section::create([
            'course_id' => $course->id,
            'name' => 'F',
            'year_level' => 2,
            'is_active' => true,
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '26-0003',
            'course_id' => $course->id,
            'section_id' => $savedSection->id,
            'year_level' => 5,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.students.index'));

        $response->assertOk();
        $response->assertViewHas('sections', function ($sections) use ($savedSection, $unusedSection) {
            return $sections->pluck('id')->all() === [$savedSection->id]
                && ! $sections->pluck('id')->contains($unusedSection->id);
        });
    }

    public function test_admin_can_delete_a_student_who_created_request_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '26-0004',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => 1,
        ]);
        $faculty = Faculty::create(['employee_number' => 'FAC-504', 'name' => 'Maria Reyes']);
        $subject = Subject::create([
            'code' => 'PF104',
            'name' => 'Programming Fundamentals',
            'course_id' => $course->id,
            'year_level' => 1,
        ]);
        $academicYear = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);
        $excuseRequest = ExcuseRequest::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'facilitator_id' => $faculty->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'absence_date' => now()->toDateString(),
            'reason_category_id' => $reason->id,
            'explanation' => 'The student was unable to attend because of illness.',
            'status' => 'draft',
        ]);
        $excuseRequest->histories()->create([
            'new_status' => 'draft',
            'action_by' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('excuse_requests', ['id' => $excuseRequest->id]);
    }
}
