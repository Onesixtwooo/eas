<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_receive_a_new_revision_after_system_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $initial = $this->actingAs($admin)
            ->getJson(route('realtime.version'))
            ->assertOk()
            ->json('revision');

        $this->actingAs($admin)->put(route('profile.update'), [
            'name' => 'Updated Administrator',
            'email' => $admin->email,
        ])->assertSessionHasNoErrors();

        $updated = $this->actingAs($admin)
            ->getJson(route('realtime.version'))
            ->assertOk()
            ->json('revision');

        $this->assertNotSame($initial, $updated);
    }

    public function test_realtime_revision_is_not_public(): void
    {
        $this->getJson(route('realtime.version'))->assertUnauthorized();
    }

    public function test_admin_can_see_active_student_presence_until_it_expires(): void
    {
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::create(['user_id' => $studentUser->id, 'student_number' => '2026-1001', 'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($studentUser)->get(route('profile'))->assertOk();
        $this->actingAs($admin)->getJson(route('realtime.presence'))
            ->assertOk()->assertJsonFragment(['online_user_ids' => [$studentUser->id]]);

        $this->travel(31)->seconds();
        $this->actingAs($admin)->getJson(route('realtime.presence'))
            ->assertOk()->assertJsonMissing(['online_user_ids' => [$studentUser->id]]);
    }
}
