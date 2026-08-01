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
        ]);

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subjects', [
            'code' => 'PF201',
            'name' => 'Object-Oriented Programming',
            'course_id' => $course->id,
            'year_level' => 2,
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
            ])
            ->assertRedirect(route('admin.subjects.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'code' => 'PF101',
            'name' => 'Introduction to Programming',
            'year_level' => 2,
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
}
