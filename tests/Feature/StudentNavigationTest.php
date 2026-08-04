<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_are_sent_to_requests_and_do_not_see_dashboard_navigation(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertRedirect(route('requests.index'));

        $this->actingAs($student)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('My Requests')
            ->assertDontSee('>Dashboard<', false);
    }

    public function test_authenticated_pages_are_not_cached_and_cannot_be_reopened_after_logout(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $protectedResponse = $this->actingAs($student)->get(route('profile'));

        $protectedResponse->assertOk();
        $this->assertStringContainsString('no-store', (string) $protectedResponse->headers->get('Cache-Control'));
        $protectedResponse->assertHeader('Pragma', 'no-cache');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('requests.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_update_their_profile_details(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)
            ->put(route('profile.update'), [
                'name' => 'Updated Student',
                'email' => 'updated@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Updated Student',
            'email' => 'updated@example.com',
            'role' => 'student',
        ]);
    }

    public function test_user_must_confirm_current_password_to_change_it(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'password' => 'old-password',
        ]);

        $this->actingAs($student)
            ->put(route('profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrors('current_password', null, 'password');

        $this->actingAs($student)
            ->put(route('profile.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-password', $student->fresh()->password));
    }
}
