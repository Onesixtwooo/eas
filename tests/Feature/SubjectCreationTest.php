<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Faculty;
use App\Models\DailyAbsenceReport;
use App\Models\InstructorAssignment;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\BsitFacultySubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_subjects_on_their_own_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        Subject::create([
            'code' => 'PF101',
            'name' => 'Programming Fundamentals',
            'course_id' => $course->id,
            'year_level' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subjects.index'))
            ->assertOk()
            ->assertSee('Subjects')
            ->assertSee('PF101')
            ->assertSee('Programming Fundamentals');
    }

    public function test_admin_can_add_a_subject_for_a_course_and_year_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create([
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'code' => 'pf201',
            'name' => 'Object-Oriented Programming',
            'course_id' => $course->id,
            'year_level' => 2,
            'semester' => 1,
        ]);

        $response->assertRedirect(route('admin.subjects.index', ['semester' => 1]));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subjects', [
            'code' => 'PF201',
            'name' => 'Object-Oriented Programming',
            'course_id' => $course->id,
            'year_level' => 2,
            'semester' => 1,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_search_and_filter_subjects_by_year_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id, 'year_level' => 1]);
        Subject::create(['code' => 'PF201', 'name' => 'Object-Oriented Programming', 'course_id' => $course->id, 'year_level' => 2]);

        $this->actingAs($admin)
            ->get(route('admin.subjects.index', ['search' => 'programming', 'year_level' => 2]))
            ->assertOk()
            ->assertSee('PF201')
            ->assertDontSee('PF101');
    }

    public function test_admin_can_edit_a_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id, 'year_level' => 1]);

        $this->actingAs($admin)
            ->put(route('admin.subjects.update', $subject), [
                'code' => 'PF101',
                'name' => 'Introduction to Programming',
                'course_id' => $course->id,
                'year_level' => 2,
                'semester' => 2,
            ])
            ->assertRedirect(route('admin.subjects.index', ['semester' => 2]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'code' => 'PF101',
            'name' => 'Introduction to Programming',
            'year_level' => 2,
            'semester' => 2,
        ]);
    }

    public function test_subject_year_change_moves_its_instructor_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id, 'year_level' => 1]);
        InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1]);

        $this->actingAs($admin)->put(route('admin.subjects.update', $subject), [
            'code' => 'PF101',
            'name' => 'Programming Fundamentals',
            'course_id' => $course->id,
            'year_level' => 2,
            'semester' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $subject->fresh()->year_level);
        $this->assertDatabaseHas('instructor_assignments', [
            'faculty_id' => $faculty->id, 'subject_id' => $subject->id,
            'year_level' => 2, 'is_active' => true, 'retired_at' => null,
        ]);
        $this->assertDatabaseMissing('instructor_assignments', [
            'faculty_id' => $faculty->id, 'subject_id' => $subject->id, 'year_level' => 1,
        ]);
    }

    public function test_subject_year_change_preserves_attendance_history_and_moves_the_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $oldSection = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $newSection = Section::create(['course_id' => $course->id, 'year_level' => 2, 'name' => 'A']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id, 'year_level' => 1]);
        $assignment = InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $oldSection->id]);
        $student = Student::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'student_number' => '26-5101', 'course_id' => $course->id,
            'section_id' => $oldSection->id, 'year_level' => 1,
        ]);
        $report = DailyAbsenceReport::create([
            'instructor_assignment_id' => $assignment->id, 'student_id' => $student->id,
            'report_date' => today(),
        ]);

        $this->actingAs($admin)->put(route('admin.subjects.update', $subject), [
            'code' => 'PF101', 'name' => 'Programming Fundamentals',
            'course_id' => $course->id, 'year_level' => 2, 'semester' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($assignment->fresh()->retired_at);
        $this->assertDatabaseHas('daily_absence_reports', ['id' => $report->id, 'instructor_assignment_id' => $assignment->id]);
        $this->assertDatabaseHas('instructor_assignments', [
            'faculty_id' => $faculty->id, 'subject_id' => $subject->id,
            'year_level' => 2, 'section_id' => $newSection->id,
            'is_active' => true, 'retired_at' => null,
        ]);
    }

    public function test_subject_year_change_requires_matching_blocks_in_the_target_year(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id, 'year_level' => 1]);
        $assignment = InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $section->id]);

        $this->actingAs($admin)->put(route('admin.subjects.update', $subject), [
            'code' => 'PF101', 'name' => 'Programming Fundamentals',
            'course_id' => $course->id, 'year_level' => 2, 'semester' => 1,
        ])->assertSessionHasErrors('year_level');

        $this->assertSame(1, $subject->fresh()->year_level);
        $this->assertDatabaseHas('instructor_assignments', ['id' => $assignment->id, 'year_level' => 1, 'retired_at' => null]);
    }

    public function test_subject_year_change_rejects_an_existing_instructor_overlap(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $first = Faculty::create(['name' => 'Maria Reyes']);
        $second = Faculty::create(['name' => 'John Cruz']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id, 'year_level' => 1]);
        $source = InstructorAssignment::create(['faculty_id' => $first->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1]);
        $target = InstructorAssignment::create(['faculty_id' => $second->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 2]);

        $this->actingAs($admin)->put(route('admin.subjects.update', $subject), [
            'code' => 'PF101', 'name' => 'Programming Fundamentals',
            'course_id' => $course->id, 'year_level' => 2, 'semester' => 1,
        ])->assertSessionHasErrors('year_level');

        $this->assertSame(1, $subject->fresh()->year_level);
        $this->assertDatabaseHas('instructor_assignments', ['id' => $source->id, 'year_level' => 1, 'retired_at' => null]);
        $this->assertDatabaseHas('instructor_assignments', ['id' => $target->id, 'year_level' => 2, 'retired_at' => null]);
    }

    public function test_reseeding_does_not_undo_a_subject_year_change(): void
    {
        $this->seed(BsitFacultySubjectSeeder::class);
        $subject = Subject::where('code', 'SPT2')->firstOrFail();
        $assignment = InstructorAssignment::where('subject_id', $subject->id)->firstOrFail();
        $subject->update(['year_level' => 3]);
        $assignment->update(['year_level' => 3]);

        $this->seed(BsitFacultySubjectSeeder::class);

        $this->assertSame(3, $subject->fresh()->year_level);
        $this->assertSame(3, $assignment->fresh()->year_level);
        $this->assertSame(1, InstructorAssignment::where('subject_id', $subject->id)->count());
    }

    public function test_resaving_a_subject_repairs_an_existing_assignment_year_mismatch(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'Maria Reyes']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $subject = Subject::create(['code' => 'SPT2', 'name' => 'Mobile Programming', 'course_id' => $course->id, 'year_level' => 3]);
        $assignment = InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 4]);

        $this->actingAs($admin)->put(route('admin.subjects.update', $subject), [
            'code' => 'SPT2', 'name' => 'Mobile Programming',
            'course_id' => $course->id, 'year_level' => 3, 'semester' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $subject->fresh()->year_level);
        $this->assertDatabaseMissing('instructor_assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('instructor_assignments', [
            'faculty_id' => $faculty->id, 'subject_id' => $subject->id,
            'year_level' => 3, 'is_active' => true, 'retired_at' => null,
        ]);
    }

    public function test_subject_code_must_be_unique_and_year_level_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create([
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
        ]);
        $course->subjects()->create([
            'code' => 'PF101',
            'name' => 'Programming Fundamentals',
            'year_level' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'code' => 'PF101',
            'name' => 'Duplicate',
            'course_id' => $course->id,
        ]);

        $response->assertSessionHasErrors(['code', 'year_level']);
        $this->assertDatabaseCount('subjects', 1);
    }

    public function test_subject_tabs_display_only_the_selected_semester(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        Subject::create(['code' => 'FIRST101', 'name' => 'First Semester Subject', 'course_id' => $course->id, 'year_level' => 1, 'semester' => 1]);
        Subject::create(['code' => 'SECOND101', 'name' => 'Second Semester Subject', 'course_id' => $course->id, 'year_level' => 1, 'semester' => 2]);

        $this->actingAs($admin)->get(route('admin.subjects.index'))
            ->assertOk()->assertSee('First Semester')->assertSee('Second Semester')
            ->assertSee('FIRST101')->assertDontSee('SECOND101');

        $this->actingAs($admin)->get(route('admin.subjects.index', ['semester' => 2]))
            ->assertOk()->assertSee('SECOND101')->assertDontSee('FIRST101');
    }
}
