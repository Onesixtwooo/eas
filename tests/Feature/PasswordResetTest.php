<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_opens_and_password_can_be_changed(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Set a new password');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Reset your password');
    }

    public function test_password_reset_request_returns_generic_message_regardless_of_account_existence(): void
    {
        $user = User::factory()->create(['email' => 'registered.student@olshco.edu.ph', 'is_active' => true]);

        $existingResponse = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $existingResponse->assertRedirect(route('password.request'));
        $existingResponse->assertSessionHas('success', 'If an account matches that email address, a password reset link has been sent.');
        $existingResponse->assertSessionMissing('error');

        $nonExistentResponse = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'unknown.ghost@olshco.edu.ph',
        ]);

        $nonExistentResponse->assertRedirect(route('password.request'));
        $nonExistentResponse->assertSessionHas('success', 'If an account matches that email address, a password reset link has been sent.');
        $nonExistentResponse->assertSessionMissing('error');
    }
}

