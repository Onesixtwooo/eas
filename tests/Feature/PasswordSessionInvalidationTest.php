<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordSessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_removes_existing_sessions_and_rotates_remember_token(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create();
        $oldToken = $user->getRememberToken();
        $this->createSession('old-session', $user);
        $this->createSession('other-session', $other);

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->assertNotSame($oldToken, $user->fresh()->getRememberToken());
    }

    public function test_profile_password_change_removes_other_sessions_but_keeps_current_login(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['role' => 'student', 'is_active' => true, 'password' => 'old-password']);
        $oldToken = $user->getRememberToken();
        $this->createSession('old-session', $user);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertNotSame($oldToken, $user->fresh()->getRememberToken());
        $this->get(route('profile'))->assertOk();
    }

    public function test_admin_password_change_removes_target_sessions_only(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $oldToken = $target->getRememberToken();
        $this->createSession('target-session', $target);
        $this->createSession('admin-session', $admin);

        $this->actingAs($admin)->put(route('admin.accounts.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['faculty'],
            'is_active' => '1',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('admin.accounts.index'));

        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
        $this->assertNotSame($oldToken, $target->fresh()->getRememberToken());
        $this->assertTrue(Hash::check('new-password', $target->fresh()->password));
    }

    public function test_session_with_an_old_password_hash_is_logged_out(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true, 'password' => 'old-password']);
        $oldHash = $user->password;

        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash]);
        $user->forceFill(['password' => 'new-password'])->save();

        $this->get(route('profile'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function createSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'payload' => base64_encode('session'),
            'last_activity' => time(),
        ]);
    }
}
