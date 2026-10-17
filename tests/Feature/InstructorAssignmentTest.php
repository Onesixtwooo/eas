<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\AcademicYear;
use App\Models\DailyAbsenceReport;
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

class InstructorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_assignment_page_includes_the_new_assignment_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.instructor-assignments.index'))
            ->assertOk()
            ->assertSee('New Assignment')
            ->assertSee('new-assignment-title', false);
    }

    public function test_admin_can_assign_multiple_subjects_to_an_instructor_for_a_year_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $instructor = Faculty::create([
            'user_id' => User::factory()->create(['role' => 'faculty', 'is_active' => true])->id,
            'employee_number' => 'FAC-100',
        ]);
        $course = Course::create([
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
        ]);
        $subjects = collect([
            Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]),
            Subject::create(['code' => 'CC105', 'name' => 'Information Management', 'course_id' => $course->id]),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.instructor-assignments.store'), [
            'faculty_id' => $instructor->id,
            'course_id' => $course->id,
            'year_level' => 2,
            'subject_ids' => $subjects->pluck('id')->all(),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', '2 subject assignment(s) saved for Year 2.');
        $this->assertDatabaseCount('instructor_assignments', 2);

        foreach ($subjects as $subject) {
            $this->assertDatabaseHas('instructor_assignments', [
                'faculty_id' => $instructor->id,
                'course_id' => $course->id,
                'subject_id' => $subject->id,
                'year_level' => 2,
                'is_active' => true,
            ]);
        }
    }

    public function test_assignment_requires_at_least_one_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $instructor = Faculty::create([
            'user_id' => User::factory()->create(['role' => 'faculty', 'is_active' => true])->id,
            'employee_number' => 'FAC-101',
        ]);
        $course = Course::create([
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.instructor-assignments.store'), [
            'faculty_id' => $instructor->id,
            'course_id' => $course->id,
            'year_level' => 1,
        ]);

        $response->assertSessionHasErrors('subject_ids');
        $this->assertDatabaseCount('instructor_assignments', 0);
    }

    public function test_instructor_can_be_assigned_to_multiple_year_levels(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $instructor = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);

        $this->actingAs($admin)
            ->post(route('admin.instructor-assignments.store'), [
                'faculty_id' => $instructor->id,
                'course_id' => $course->id,
                'year_levels' => [1, 3],
                'subject_ids' => [$subject->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '2 subject assignment(s) saved for Year 1, Year 3.');

        $this->assertDatabaseHas('instructor_assignments', ['faculty_id' => $instructor->id, 'subject_id' => $subject->id, 'year_level' => 1]);
        $this->assertDatabaseHas('instructor_assignments', ['faculty_id' => $instructor->id, 'subject_id' => $subject->id, 'year_level' => 3]);
    }

    public function test_admin_can_edit_an_instructor_assignment_group(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $instructor = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        InstructorAssignment::create(['faculty_id' => $instructor->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1]);

        $this->actingAs($admin)
            ->put(route('admin.instructor-assignments.group-update', [$instructor->id, $course->id]), [
                'year_levels' => [1, 3],
                'subject_ids' => [$subject->id],
            ])
            ->assertRedirect(route('admin.instructor-assignments.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('instructor_assignments', 2);
        $this->assertDatabaseHas('instructor_assignments', ['faculty_id' => $instructor->id, 'subject_id' => $subject->id, 'year_level' => 3]);
    }

    public function test_edit_keeps_existing_assignment_and_its_attendance_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        $added = Subject::create(['code' => 'CC105', 'name' => 'Information Management', 'course_id' => $course->id]);
        $assignment = InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1]);
        $student = Student::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'student_number' => '26-5001', 'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1,
        ]);
        $report = DailyAbsenceReport::create([
            'instructor_assignment_id' => $assignment->id, 'student_id' => $student->id,
            'report_date' => today(), 'adviser_comment' => 'Contacted the student.',
        ]);

        $this->actingAs($admin)->put(route('admin.instructor-assignments.group-update', [$faculty->id, $course->id]), [
            'year_levels' => [1], 'subject_ids' => [$subject->id, $added->id],
        ])->assertRedirect(route('admin.instructor-assignments.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('instructor_assignments', ['id' => $assignment->id, 'is_active' => true, 'retired_at' => null]);
        $this->assertDatabaseHas('daily_absence_reports', ['id' => $report->id, 'adviser_comment' => 'Contacted the student.']);
        $this->assertDatabaseCount('instructor_assignments', 2);
    }

    public function test_removing_assignment_retires_it_without_deleting_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        $keep = Subject::create(['code' => 'CC105', 'name' => 'Information Management', 'course_id' => $course->id]);
        $assignment = InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1]);
        InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'subject_id' => $keep->id, 'year_level' => 1]);
        $student = Student::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'student_number' => '26-5002', 'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1,
        ]);
        $report = DailyAbsenceReport::create([
            'instructor_assignment_id' => $assignment->id, 'student_id' => $student->id, 'report_date' => today(),
        ]);

        $this->actingAs($admin)->put(route('admin.instructor-assignments.group-update', [$faculty->id, $course->id]), [
            'year_levels' => [1], 'subject_ids' => [$keep->id],
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($assignment->fresh()->retired_at);
        $this->assertFalse($assignment->fresh()->is_active);
        $this->assertDatabaseHas('daily_absence_reports', ['id' => $report->id]);
        $this->actingAs($admin)->patch(route('admin.instructor-assignments.group-toggle', [$faculty->id, $course->id]));
        $this->assertFalse($assignment->fresh()->is_active);

        $this->actingAs($admin)->delete(route('admin.instructor-assignments.group-destroy', [$faculty->id, $course->id]))
            ->assertRedirect();
        $this->assertDatabaseHas('daily_absence_reports', ['id' => $report->id]);
        $this->assertNotNull($assignment->fresh()->retired_at);
    }

    public function test_admin_can_assign_the_same_subject_to_another_instructor(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $currentInstructor = Faculty::create(['name' => 'Maria Reyes']);
        $newInstructor = Faculty::create(['name' => 'Elizor Villanueva']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $blockA = Section::create(['course_id' => $course->id, 'year_level' => 3, 'name' => 'A']);
        $blockB = Section::create(['course_id' => $course->id, 'year_level' => 3, 'name' => 'B']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        InstructorAssignment::create(['faculty_id' => $currentInstructor->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 3, 'section_id' => $blockA->id]);

        $this->actingAs($admin)
            ->post(route('admin.instructor-assignments.store'), [
                'faculty_id' => $newInstructor->id,
                'course_id' => $course->id,
                'year_levels' => [3],
                'subject_block_selection_submitted' => 1,
                'subject_blocks' => [$subject->id => ['B']],
                'subject_ids' => [$subject->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('instructor_assignments', 2);
        $this->assertDatabaseHas('instructor_assignments', [
            'faculty_id' => $currentInstructor->id,
            'subject_id' => $subject->id,
            'year_level' => 3,
            'section_id' => $blockA->id,
        ]);
        $this->assertDatabaseHas('instructor_assignments', [
            'faculty_id' => $newInstructor->id,
            'subject_id' => $subject->id,
            'year_level' => 3,
            'section_id' => $blockB->id,
        ]);
    }

    public function test_each_subject_can_have_its_own_blocks(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $blockA = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $blockB = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'B']);
        $first = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        $second = Subject::create(['code' => 'CC105', 'name' => 'Information Management', 'course_id' => $course->id]);

        $this->actingAs($admin)->post(route('admin.instructor-assignments.store'), [
            'faculty_id' => $faculty->id, 'course_id' => $course->id, 'year_levels' => [1],
            'subject_ids' => [$first->id, $second->id],
            'subject_block_selection_submitted' => 1,
            'subject_blocks' => [$first->id => ['A'], $second->id => ['B']],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('instructor_assignments', 2);
        $this->assertDatabaseHas('instructor_assignments', ['subject_id' => $first->id, 'section_id' => $blockA->id]);
        $this->assertDatabaseHas('instructor_assignments', ['subject_id' => $second->id, 'section_id' => $blockB->id]);
        $this->actingAs($admin)->get(route('admin.instructor-assignments.index', [
            'edit_faculty' => $faculty->id, 'edit_course' => $course->id,
        ]))->assertOk()->assertSee('name="subject_blocks['.$first->id.'][]"', false)
            ->assertSee('name="subject_blocks['.$second->id.'][]"', false);

        $this->actingAs($admin)->put(route('admin.instructor-assignments.group-update', [$faculty->id, $course->id]), [
            'year_levels' => [1], 'subject_ids' => [$first->id, $second->id],
            'subject_block_selection_submitted' => 1,
            'subject_blocks' => [$first->id => ['A'], $second->id => ['A', 'B']],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('instructor_assignments', 3);
        $this->assertDatabaseHas('instructor_assignments', ['subject_id' => $first->id, 'section_id' => $blockA->id]);
        $this->assertDatabaseHas('instructor_assignments', ['subject_id' => $second->id, 'section_id' => $blockA->id]);
        $this->assertDatabaseHas('instructor_assignments', ['subject_id' => $second->id, 'section_id' => $blockB->id]);
    }

    public function test_form_requires_blocks_for_every_selected_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $first = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        $second = Subject::create(['code' => 'CC105', 'name' => 'Information Management', 'course_id' => $course->id]);

        $this->actingAs($admin)->post(route('admin.instructor-assignments.store'), [
            'faculty_id' => $faculty->id, 'course_id' => $course->id, 'year_levels' => [1],
            'subject_ids' => [$first->id, $second->id], 'subject_block_selection_submitted' => 1,
            'subject_blocks' => [$first->id => ['all']],
        ])->assertSessionHasErrors('subject_blocks.'.$second->id);

        $this->assertDatabaseCount('instructor_assignments', 0);
    }

    public function test_admin_cannot_assign_overlapping_instructors_to_the_same_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $first = Faculty::create(['name' => 'First Instructor']);
        $second = Faculty::create(['name' => 'Second Instructor']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $block = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        InstructorAssignment::create([
            'faculty_id' => $first->id, 'course_id' => $course->id,
            'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $block->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.instructor-assignments.store'), [
            'faculty_id' => $second->id, 'course_id' => $course->id,
            'year_levels' => [1], 'subject_ids' => [$subject->id],
            'subject_block_selection_submitted' => 1,
            'subject_blocks' => [$subject->id => ['A']],
        ])->assertSessionHasErrors('subject_blocks');

        $this->assertDatabaseCount('instructor_assignments', 1);
    }

    public function test_student_request_uses_the_instructor_assigned_to_their_block(): void
    {
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $blockA = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $blockB = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'B']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        $first = Faculty::create(['name' => 'First Instructor']);
        $second = Faculty::create(['name' => 'Second Instructor']);
        $fallback = Faculty::create(['name' => 'Legacy All-Block Instructor']);
        InstructorAssignment::create(['faculty_id' => $first->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $blockA->id, 'is_active' => true]);
        InstructorAssignment::create(['faculty_id' => $second->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $blockB->id, 'is_active' => true]);
        InstructorAssignment::create(['faculty_id' => $fallback->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1, 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::create(['user_id' => $studentUser->id, 'student_number' => '26-6101', 'course_id' => $course->id, 'section_id' => $blockB->id, 'year_level' => 1]);

        $this->actingAs($studentUser)->get(route('requests.create'))
            ->assertOk()
            ->assertViewHas('assignments', fn ($assignments) =>
                $assignments->count() === 1 && $assignments->first()->faculty_id === $second->id);

        AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);
        $this->actingAs($studentUser)->post(route('requests.store'), [
            'absence_date' => today()->toDateString(),
            'subject_ids' => [$subject->id],
            'reason_category_id' => $reason->id,
            'explanation' => 'The student was unable to attend the scheduled class today.',
            'declaration' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('excuse_requests', [
            'student_id' => $studentUser->student->id,
            'subject_id' => $subject->id,
            'facilitator_id' => $second->id,
        ]);
    }

    public function test_disabled_assignment_cannot_be_reenabled_over_another_instructors_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $first = Faculty::create(['name' => 'First Instructor']);
        $second = Faculty::create(['name' => 'Second Instructor']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $block = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        $old = InstructorAssignment::create(['faculty_id' => $first->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $block->id, 'is_active' => false]);
        InstructorAssignment::create(['faculty_id' => $second->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $block->id, 'is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.instructor-assignments.toggle', $old))
            ->assertSessionHasErrors('block_names');

        $this->assertFalse($old->fresh()->is_active);
    }

    public function test_group_cannot_enable_overlapping_all_block_and_specific_block_assignments(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $block = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        $all = InstructorAssignment::create([
            'faculty_id' => $faculty->id, 'course_id' => $course->id,
            'subject_id' => $subject->id, 'year_level' => 1, 'is_active' => false,
        ]);
        $specific = InstructorAssignment::create([
            'faculty_id' => $faculty->id, 'course_id' => $course->id,
            'subject_id' => $subject->id, 'year_level' => 1,
            'section_id' => $block->id, 'is_active' => false,
        ]);

        $this->actingAs($admin)->patch(route('admin.instructor-assignments.group-toggle', [$faculty->id, $course->id]))
            ->assertSessionHasErrors('block_names');

        $this->assertFalse($all->fresh()->is_active);
        $this->assertFalse($specific->fresh()->is_active);
    }

    public function test_table_groups_an_instructors_subjects_by_course_and_year_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $instructor = Faculty::create([
            'name' => 'Maria Reyes',
            'employee_number' => 'FAC-102',
        ]);
        $course = Course::create([
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
        ]);
        $subjects = collect([
            Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id, 'year_level' => 1]),
            Subject::create(['code' => 'CC105', 'name' => 'Information Management', 'course_id' => $course->id, 'year_level' => 1]),
        ]);

        foreach ($subjects as $subject) {
            InstructorAssignment::create([
                'faculty_id' => $instructor->id,
                'course_id' => $course->id,
                'subject_id' => $subject->id,
                'year_level' => 1,
                'is_active' => true,
            ]);
        }
        InstructorAssignment::create([
            'faculty_id' => $instructor->id,
            'course_id' => $course->id,
            'subject_id' => $subjects->first()->id,
            'year_level' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.instructor-assignments.index'));

        $response->assertOk();
        $response->assertViewHas('assignments', function ($assignments) use ($subjects) {
            return $assignments->count() === 1
                && $assignments->first()->pluck('subject_id')->unique()->sort()->values()->all()
                    === $subjects->pluck('id')->sort()->values()->all();
        });
        $response->assertSee('PF101');
        $response->assertSee('CC105');
        $response->assertSee('Year 1');
        $response->assertSee('Year 2');
    }
}
