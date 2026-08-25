<?php

namespace Tests\Feature;

use App\Mail\AdministratorAccountCreated;
use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdviserAdvisoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_adviser_with_an_advisory_year_level(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'name' => 'Year Two Adviser',
            'email' => 'adviser@example.com',
            'roles' => ['adviser'],
            'adviser_year_level' => 2,
        ])->assertRedirect(route('admin.accounts.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'adviser@example.com',
            'role' => 'adviser',
            'adviser_year_level' => 2,
        ]);
        Mail::assertSent(AdministratorAccountCreated::class);
    }

    public function test_adviser_role_requires_an_advisory_year_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'name' => 'Unassigned Adviser',
            'email' => 'unassigned@example.com',
            'roles' => ['adviser'],
        ])->assertSessionHasErrors('adviser_year_level');

        $this->assertDatabaseMissing('users', ['email' => 'unassigned@example.com']);
    }

    public function test_adviser_only_sees_students_in_the_assigned_year_level(): void
    {
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $yearTwo = Section::create(['course_id' => $course->id, 'year_level' => 2, 'name' => 'A']);
        $yearThree = Section::create(['course_id' => $course->id, 'year_level' => 3, 'name' => 'A']);
        $this->createStudent('Visible Student', '26-2001', $course, $yearTwo);
        $this->createStudent('Hidden Student', '26-3001', $course, $yearThree);
        $adviser = User::factory()->create([
            'role' => 'adviser',
            'roles' => ['adviser'],
            'adviser_year_level' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($adviser)->get(route('dashboard'))
            ->assertRedirect(route('advisory.index'));

        $this->actingAs($adviser)->get(route('advisory.index'))
            ->assertOk()
            ->assertSee('My Advisory')
            ->assertSee('All Blocks')
            ->assertSee('Block A')
            ->assertSee('advisory-table-wrap', false)
            ->assertSee('advisory-col-student', false)
            ->assertSee('Visible Student')
            ->assertDontSee('Hidden Student')
            ->assertDontSee('Absent Today')
            ->assertSee('Daily Absences')
            ->assertDontSee('Delete');
    }

    public function test_adviser_can_filter_the_advisory_using_block_tabs(): void
    {
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $blockA = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $blockB = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'B']);
        $this->createStudent('Block A Student', '26-1001', $course, $blockA);
        $this->createStudent('Block B Student', '26-1002', $course, $blockB);
        $adviser = User::factory()->create([
            'role' => 'adviser',
            'roles' => ['adviser'],
            'adviser_year_level' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($adviser)->get(route('advisory.index', ['section_id' => $blockB->id]))
            ->assertOk()
            ->assertSee('Block B Student')
            ->assertDontSee('Block A Student');
    }

    public function test_advisory_tabs_only_show_blocks_with_saved_students(): void
    {
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $occupiedBlock = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'B']);
        $this->createStudent('Saved Student', '26-1001', $course, $occupiedBlock);
        $adviser = User::factory()->create([
            'role' => 'adviser',
            'roles' => ['adviser'],
            'adviser_year_level' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($adviser)->get(route('advisory.index'))
            ->assertOk()
            ->assertSee('Block A')
            ->assertDontSee('Block B');
    }

    public function test_adviser_cannot_access_administrative_students_or_all_requests(): void
    {
        $adviser = User::factory()->create([
            'role' => 'adviser',
            'roles' => ['adviser'],
            'adviser_year_level' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($adviser)->get(route('admin.students.index'))->assertForbidden();
        $this->actingAs($adviser)->get(route('requests.index'))->assertForbidden();
    }

    private function createStudent(string $name, string $number, Course $course, Section $section): Student
    {
        $user = User::factory()->create(['name' => $name, 'role' => 'student', 'is_active' => true]);

        return Student::create([
            'user_id' => $user->id,
            'student_number' => $number,
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => $section->year_level,
        ]);
    }
}
