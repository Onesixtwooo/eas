<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DailyAbsenceReport;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_save_todays_absent_students_for_an_assigned_class(): void
    {
        [$facultyUser, $assignment, $assignedStudent] = $this->assignedClass();

        $this->actingAs($facultyUser)->get(route('student-reports.index'))
            ->assertOk()
            ->assertSee('Student Report')
            ->assertSee('Assigned Classes')
            ->assertSee('aria-label="Assigned classes"', false)
            ->assertDontSee('<select id="assignment_id"', false)
            ->assertSee($assignedStudent->user->name);

        $this->actingAs($facultyUser)->post(route('student-reports.store'), [
            'assignment_id' => $assignment->id,
            'absent_student_ids' => [$assignedStudent->id],
            'comments' => [$assignedStudent->id => 'Reported absent during the morning class.'],
        ])->assertRedirect(route('student-reports.index'));

        $this->assertTrue(DailyAbsenceReport::query()
            ->where('instructor_assignment_id', $assignment->id)
            ->where('student_id', $assignedStudent->id)
            ->whereDate('report_date', today())
            ->exists());
        $this->assertSame(
            'Reported absent during the morning class.',
            DailyAbsenceReport::firstOrFail()->comment
        );
    }

    public function test_instructor_can_select_active_class_tab(): void
    {
        [$facultyUser, $assignment] = $this->assignedClass();

        $this->actingAs($facultyUser)
            ->post(route('student-reports.select'), ['class_key' => $assignment->class_key])
            ->assertRedirect(route('student-reports.index'));

        $this->assertSame($assignment->id, session('active_student_report_assignment_id'));
    }

    public function test_instructor_cannot_select_another_instructors_assignment(): void
    {
        [, $assignment] = $this->assignedClass();
        $otherFacultyUser = User::factory()->create(['role' => 'faculty', 'roles' => ['faculty'], 'is_active' => true]);
        Faculty::create(['user_id' => $otherFacultyUser->id, 'name' => $otherFacultyUser->name]);

        $this->actingAs($otherFacultyUser)
            ->post(route('student-reports.select'), ['class_key' => $assignment->class_key])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_instructor_cannot_report_a_student_outside_the_assigned_class(): void
    {
        [$facultyUser, $assignment, $assignedStudent, $otherStudent] = $this->assignedClass();

        $this->actingAs($facultyUser)->post(route('student-reports.store'), [
            'assignment_id' => $assignment->id,
            'absent_student_ids' => [$otherStudent->id],
        ])->assertForbidden();

        $this->assertDatabaseCount('daily_absence_reports', 0);
    }

    public function test_adviser_sees_todays_reported_absence_in_the_correct_year_and_block(): void
    {
        [$facultyUser, $assignment, $assignedStudent, $otherStudent] = $this->assignedClass();
        DailyAbsenceReport::create([
            'instructor_assignment_id' => $assignment->id,
            'student_id' => $assignedStudent->id,
            'report_date' => today(),
            'comment' => 'Did not attend the scheduled class.',
        ]);
        $adviser = User::factory()->create([
            'role' => 'adviser',
            'roles' => ['adviser'],
            'adviser_year_level' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($adviser)->get(route('advisory.absences', ['section_id' => $assignedStudent->section_id]))
            ->assertOk()
            ->assertSee('Absent Today')
            ->assertSee('Acknowledge')
            ->assertSee('Mark Resolved')
            ->assertSee('Flag Incorrect')
            ->assertSee($assignedStudent->user->name)
            ->assertSee($assignment->subject->code)
            ->assertSee('Did not attend the scheduled class.')
            ->assertDontSee($otherStudent->user->name);
    }

    public function test_saving_an_empty_checklist_clears_todays_report_for_that_class(): void
    {
        [$facultyUser, $assignment, $assignedStudent] = $this->assignedClass();
        DailyAbsenceReport::create([
            'instructor_assignment_id' => $assignment->id,
            'student_id' => $assignedStudent->id,
            'report_date' => today(),
        ]);

        $this->actingAs($facultyUser)->post(route('student-reports.store'), [
            'assignment_id' => $assignment->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('daily_absence_reports', 0);
    }

    public function test_adviser_can_comment_back_and_the_instructor_sees_the_response(): void
    {
        [$facultyUser, $assignment, $assignedStudent] = $this->assignedClass();
        $report = DailyAbsenceReport::create([
            'instructor_assignment_id' => $assignment->id,
            'student_id' => $assignedStudent->id,
            'report_date' => today(),
            'comment' => 'Student was not in class.',
        ]);
        $adviser = User::factory()->create([
            'role' => 'adviser',
            'roles' => ['adviser'],
            'adviser_year_level' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($adviser)->patch(route('advisory.absences.comment', $report), [
            'adviser_comment' => 'I contacted the student and guardian.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($adviser)->patch(route('advisory.absences.action', $report), [
            'adviser_action' => 'resolved',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame('I contacted the student and guardian.', $report->adviser_comment);
        $this->assertSame($adviser->id, $report->adviser_commented_by);
        $this->assertNotNull($report->adviser_commented_at);
        $this->assertSame('resolved', $report->adviser_action);
        $this->assertSame($adviser->id, $report->adviser_action_by);
        $this->assertNotNull($report->adviser_action_at);

        $this->actingAs($facultyUser)->get(route('student-reports.index'))
            ->assertOk()
            ->assertSee('Adviser action:')
            ->assertSee('Resolved')
            ->assertSee('Adviser response:')
            ->assertSee('I contacted the student and guardian.');

        $this->actingAs($facultyUser)->post(route('student-reports.store'), [
            'assignment_id' => $assignment->id,
            'absent_student_ids' => [$assignedStudent->id],
            'comments' => [$assignedStudent->id => 'Updated instructor comment.'],
        ])->assertRedirect();
        $this->assertSame('resolved', $report->fresh()->adviser_action);
    }

    public function test_adviser_cannot_reply_to_another_year_levels_report(): void
    {
        [, $assignment, $assignedStudent] = $this->assignedClass();
        $report = DailyAbsenceReport::create([
            'instructor_assignment_id' => $assignment->id,
            'student_id' => $assignedStudent->id,
            'report_date' => today(),
        ]);
        $otherAdviser = User::factory()->create([
            'role' => 'adviser',
            'roles' => ['adviser'],
            'adviser_year_level' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($otherAdviser)->patch(route('advisory.absences.comment', $report), [
            'adviser_comment' => 'Unauthorized reply',
        ])->assertForbidden();

        $this->assertNull($report->fresh()->adviser_comment);
    }

    private function assignedClass(): array
    {
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $blockA = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A', 'is_active' => true]);
        $blockB = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'B', 'is_active' => true]);
        $subject = Subject::create(['course_id' => $course->id, 'code' => 'IT101', 'name' => 'Introduction to IT', 'year_level' => 1, 'is_active' => true]);
        $facultyUser = User::factory()->create(['role' => 'faculty', 'roles' => ['faculty'], 'is_active' => true]);
        $faculty = Faculty::create(['user_id' => $facultyUser->id, 'name' => $facultyUser->name]);
        $assignment = InstructorAssignment::create([
            'faculty_id' => $faculty->id,
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'year_level' => 1,
            'section_id' => $blockA->id,
            'is_active' => true,
        ]);
        $assignedStudent = $this->student('Assigned Student', '26-1001', $course, $blockA);
        $otherStudent = $this->student('Other Block Student', '26-1002', $course, $blockB);

        return [$facultyUser, $assignment->load('subject'), $assignedStudent, $otherStudent];
    }

    private function student(string $name, string $number, Course $course, Section $section): Student
    {
        $user = User::factory()->create(['name' => $name, 'role' => 'student', 'is_active' => true]);

        return Student::create([
            'user_id' => $user->id,
            'student_number' => $number,
            'student_type' => 'regular',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => $section->year_level,
        ])->load('user');
    }
}
