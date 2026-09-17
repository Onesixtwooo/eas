<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\ExcuseRequest;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
use App\Models\ReasonCategory;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacultyRequestVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_only_sees_confirmed_requests_for_their_assigned_subjects(): void
    {
        $facultyUser = User::factory()->create(['role' => 'faculty', 'roles' => ['faculty'], 'is_active' => true]);
        $faculty = Faculty::create(['user_id' => $facultyUser->id, 'name' => 'Assigned Instructor']);
        $otherFaculty = Faculty::create(['name' => 'Other Instructor']);
        $studentUser = User::factory()->create(['name' => 'Visible Student', 'role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create(['user_id' => $studentUser->id, 'student_number' => '2026-2001', 'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1]);
        $subject = Subject::create(['code' => 'IT101', 'name' => 'Computing', 'course_id' => $course->id, 'year_level' => 1]);
        $secondSubject = Subject::create(['code' => 'IT102', 'name' => 'Programming', 'course_id' => $course->id, 'year_level' => 1]);
        $otherSubject = Subject::create(['code' => 'IT999', 'name' => 'Unassigned Subject', 'course_id' => $course->id, 'year_level' => 1]);
        InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1, 'is_active' => true]);
        InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'subject_id' => $secondSubject->id, 'year_level' => 1, 'is_active' => true]);
        $year = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);
        $base = ['student_id' => $student->id, 'subject_id' => $subject->id, 'academic_year_id' => $year->id, 'semester_id' => $semester->id, 'absence_date' => now(), 'reason_category_id' => $reason->id, 'explanation' => 'A sufficiently detailed request explanation.'];

        $visible = ExcuseRequest::create($base + ['facilitator_id' => $faculty->id, 'reference_number' => 'EAS-VISIBLE', 'status' => 'approved', 'approved_at' => now()]);
        $pending = ExcuseRequest::create($base + ['facilitator_id' => $faculty->id, 'reference_number' => 'EAS-PENDING', 'status' => 'submitted', 'submitted_at' => now()]);
        $other = ExcuseRequest::create($base + ['facilitator_id' => $otherFaculty->id, 'reference_number' => 'EAS-OTHER', 'status' => 'approved', 'approved_at' => now()]);
        $secondVisible = ExcuseRequest::create(array_merge($base, ['subject_id' => $secondSubject->id, 'facilitator_id' => $faculty->id, 'reference_number' => 'EAS-SECOND', 'status' => 'approved', 'approved_at' => now()]));
        ExcuseRequest::create(array_merge($base, ['subject_id' => $secondSubject->id, 'facilitator_id' => $faculty->id, 'reference_number' => 'EAS-COMPLETED', 'status' => 'completed', 'approved_at' => now()->subHour(), 'completed_at' => now()]));

        $this->actingAs($facultyUser)->get(route('requests.index'))
            ->assertOk()
            ->assertSee('All Confirmed')
            ->assertSee('All Subjects')
            ->assertSee('IT101')
            ->assertSee('IT102')
            ->assertSee('EAS-VISIBLE')
            ->assertSee('EAS-SECOND')
            ->assertDontSee('EAS-PENDING')
            ->assertDontSee('EAS-OTHER');

        $this->actingAs($facultyUser)->get(route('requests.index', ['subject_id' => $subject->id]))
            ->assertOk()
            ->assertSee('EAS-VISIBLE')
            ->assertDontSee('EAS-SECOND');
        $this->actingAs($facultyUser)->get(route('requests.index', ['subject_id' => $otherSubject->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($facultyUser)->get(route('requests.show', $visible))
            ->assertOk()
            ->assertDontSee('Facilitator Acknowledgment')
            ->assertDontSee('Acknowledge & Admit Student');
        $this->actingAs($facultyUser)->get(route('requests.show', $pending))->assertForbidden();
        $this->actingAs($facultyUser)->get(route('requests.show', $other))->assertForbidden();

        $this->actingAs($facultyUser)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Confirmed Requests')
            ->assertSee('Approved Requests')
            ->assertSee('Confirmed Request Activity')
            ->assertSee('Recent Approved Activity')
            ->assertSee('Students with recently approved requests')
            ->assertDontSee('Students with the latest request activity')
            ->assertDontSee('Facilitator Acknowledgment')
            ->assertDontSee('Returned or Rejected')
            ->assertViewHas('requests', fn ($requests) => $requests->isNotEmpty() && $requests->every(fn ($request) => $request->status === 'approved'))
            ->assertViewHas('totalRequests', 3)
            ->assertViewHas('assignedStudents', 1);
    }
}
