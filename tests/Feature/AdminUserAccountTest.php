<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_login_accounts(): void
    {
        $admin = User::factory()->create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
        User::factory()->create([
            'name' => 'Disabled Faculty',
            'email' => 'faculty@example.com',
            'role' => 'faculty',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee('User Accounts')
            ->assertSee('admin@example.com')
            ->assertSee('Can log in')
            ->assertSee('faculty@example.com')
            ->assertSee('Disabled');
    }

    public function test_non_admin_cannot_view_login_accounts(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)
            ->get(route('admin.accounts.index'))
            ->assertForbidden();
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->post(route('login.attempt'), [
            'email' => 'disabled@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'Your account is disabled, please report to the IT office.',
        ]);

        $this->assertGuest();
    }

    public function test_signed_in_user_is_logged_out_when_their_account_is_disabled(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => false,
        ]);

        $this->actingAs($student)
            ->get(route('profile'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Your account is disabled, please report to the IT office.',
            ]);

        $this->assertGuest();
    }

    public function test_admin_can_edit_an_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $account = User::factory()->create(['role' => 'faculty', 'is_active' => true]);

        $this->actingAs($admin)
            ->put(route('admin.accounts.update', $account), [
                'name' => 'Updated Faculty',
                'email' => 'updated@example.com',
                'role' => 'faculty',
                'is_active' => '0',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('admin.accounts.index'));

        $account->refresh();
        $this->assertSame('Updated Faculty', $account->name);
        $this->assertSame('updated@example.com', $account->email);
        $this->assertFalse($account->is_active);
    }

    public function test_admin_can_create_another_administrator(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.accounts.store'), [
                'name' => 'Second Administrator',
                'email' => 'second.admin@example.com',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
            ])
            ->assertRedirect(route('admin.accounts.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Second Administrator',
            'email' => 'second.admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_delete_an_account_but_not_their_own(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $account = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.accounts.destroy', $account))
            ->assertRedirect(route('admin.accounts.index'));
        $this->assertDatabaseMissing('users', ['id' => $account->id]);

        $this->actingAs($admin)
            ->delete(route('admin.accounts.destroy', $admin))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
