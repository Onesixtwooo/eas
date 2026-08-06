<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
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
