<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
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

class InstructorScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_attendance_reporting_is_unavailable(): void
    {
        $instructor = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        Faculty::create(['user_id' => $instructor->id, 'name' => $instructor->name]);

        $this->actingAs($instructor)->get(route('dashboard'))
            ->assertOk()->assertDontSee('Student report')->assertDontSee('Student Report');
        $this->getJson('/student-reports')->assertNotFound();
        $this->postJson('/student-reports', ['attendance' => []])->assertNotFound();
        $this->postJson('/student-reports/select', ['class_key' => 'any'])->assertNotFound();
    }

    public function test_instructor_requests_show_only_slips_addressed_to_them(): void
    {
        $instructor = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $otherInstructor = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $faculty = Faculty::create(['user_id' => $instructor->id, 'name' => $instructor->name]);
        $otherFaculty = Faculty::create(['user_id' => $otherInstructor->id, 'name' => $otherInstructor->name]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $subject = Subject::create(['code' => 'IT101', 'name' => 'Programming', 'course_id' => $course->id]);
        $year = AcademicYear::create(['name' => '2026-2027']);
        $semester = Semester::create(['name' => 'First']);
        $reason = ReasonCategory::create(['name' => 'Illness']);
        $requestFor = function (string $name, string $number, Faculty $recipient) use ($course, $section, $subject, $year, $semester, $reason) {
            $studentUser = User::factory()->create(['name' => $name, 'role' => 'student', 'is_active' => true]);
            $student = Student::create(['user_id' => $studentUser->id, 'student_number' => $number,
                'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1]);
            return ExcuseRequest::create(['student_id' => $student->id, 'subject_id' => $subject->id,
                'facilitator_id' => $recipient->id, 'academic_year_id' => $year->id,
                'semester_id' => $semester->id, 'reason_category_id' => $reason->id,
                'absence_date' => today()->toDateString(), 'explanation' => 'Illness', 'status' => 'approved']);
        };
        $mine = $requestFor('Slip For This Instructor', '26-1001', $faculty);
        $notMine = $requestFor('Slip For Other Instructor', '26-1002', $otherFaculty);

        $this->actingAs($instructor)->get(route('requests.index'))
            ->assertOk()->assertSee('Slip For This Instructor')->assertDontSee('Slip For Other Instructor');
        $this->get(route('requests.show', $mine))->assertOk();
        $this->get(route('requests.show', $notMine))->assertForbidden();
    }
}
