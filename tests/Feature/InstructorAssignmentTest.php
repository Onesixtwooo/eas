<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
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

    public function test_admin_can_assign_the_same_subject_to_another_instructor(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $currentInstructor = Faculty::create(['name' => 'Maria Reyes']);
        $newInstructor = Faculty::create(['name' => 'Elizor Villanueva']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id]);
        InstructorAssignment::create(['faculty_id' => $currentInstructor->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 3]);

        $this->actingAs($admin)
            ->post(route('admin.instructor-assignments.store'), [
                'faculty_id' => $newInstructor->id,
                'course_id' => $course->id,
                'year_levels' => [3],
                'subject_ids' => [$subject->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('instructor_assignments', 2);
        $this->assertDatabaseHas('instructor_assignments', [
            'faculty_id' => $currentInstructor->id,
            'subject_id' => $subject->id,
            'year_level' => 3,
        ]);
        $this->assertDatabaseHas('instructor_assignments', [
            'faculty_id' => $newInstructor->id,
            'subject_id' => $subject->id,
            'year_level' => 3,
        ]);
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
