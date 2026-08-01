<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_an_instructor_to_the_assignment_list_without_an_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.instructors.store'), [
            'name' => 'Maria Santos Reyes',
            'designation' => 'Course Facilitator',
        ]);

        $response->assertRedirect(route('admin.instructor-assignments.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('faculty', [
            'user_id' => null,
            'employee_number' => null,
            'name' => 'Maria Santos Reyes',
            'designation' => 'Course Facilitator',
        ]);
        $this->assertDatabaseCount('users', 1);

        $instructor = Faculty::where('name', 'Maria Santos Reyes')->firstOrFail();
        $this->assertSame('Maria Santos Reyes', $instructor->display_name);
    }

    public function test_instructor_can_be_added_without_an_employee_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.instructors.store'), [
            'name' => 'Another Instructor',
            'designation' => 'Course Facilitator',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('faculty', 1);
    }

    public function test_instructor_assignments_page_includes_the_add_instructor_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.instructor-assignments.index'))
            ->assertOk()
            ->assertSee('Add Instructor')
            ->assertSee('modal-instructor-name', false);
    }
}
