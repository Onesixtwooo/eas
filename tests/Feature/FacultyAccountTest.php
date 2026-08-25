<?php

namespace Tests\Feature;

use App\Mail\AdministratorAccountCreated;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FacultyAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_new_linked_faculty_account(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.faculty.store'), [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'designation' => 'Course Facilitator',
            'employee_number' => 'FAC-001',
        ])->assertRedirect(route('admin.faculty.index'))->assertSessionHasNoErrors();

        $user = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('faculty', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('faculty', ['user_id' => $user->id, 'name' => 'Maria Santos']);
        Mail::assertSent(AdministratorAccountCreated::class, fn ($mail) => $mail->hasTo('maria@example.com'));
    }

    public function test_admin_can_connect_an_existing_instructor_to_a_login_account(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'Existing Instructor', 'designation' => 'Instructor']);

        $this->actingAs($admin)->post(route('admin.faculty.store'), [
            'faculty_id' => $faculty->id,
            'email' => 'existing@example.com',
        ])->assertRedirect(route('admin.faculty.index'))->assertSessionHasNoErrors();

        $this->assertSame('existing@example.com', $faculty->refresh()->user->email);
        $this->assertSame('faculty', $faculty->user->role);
    }

    public function test_current_admin_can_connect_their_account_to_an_existing_instructor(): void
    {
        Mail::fake();
        $admin = User::factory()->create([
            'name' => 'Odiether Catabona',
            'email' => 'catabona@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $faculty = Faculty::create(['name' => 'Mr. Odiether A. Catabona', 'designation' => 'Course Facilitator']);

        $this->actingAs($admin)->post(route('admin.faculty.store'), [
            'faculty_id' => $faculty->id,
            'email' => 'catabona@example.com',
        ])->assertRedirect(route('admin.faculty.index'))->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('admin', $admin->role);
        $this->assertSame(['admin', 'faculty'], $admin->roles);
        $this->assertSame($admin->id, $faculty->refresh()->user_id);
        Mail::assertNothingSent();
    }

    public function test_admin_can_replace_an_empty_duplicate_faculty_profile_with_the_assigned_profile(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['email' => 'dual@example.com', 'role' => 'admin', 'roles' => ['admin', 'faculty'], 'is_active' => true]);
        $duplicate = Faculty::create(['user_id' => $admin->id, 'name' => 'Admin Name', 'designation' => 'Course Facilitator']);
        $assigned = Faculty::create(['name' => 'Assigned Instructor', 'designation' => 'Course Facilitator']);

        $this->actingAs($admin)->post(route('admin.faculty.store'), [
            'faculty_id' => $assigned->id,
            'email' => 'dual@example.com',
        ])->assertRedirect(route('admin.faculty.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('faculty', ['id' => $duplicate->id]);
        $this->assertSame($admin->id, $assigned->refresh()->user_id);
        Mail::assertNothingSent();
    }

    public function test_faculty_navigation_is_shown_to_administrators(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.faculty.index'))
            ->assertOk()
            ->assertSee('Faculty')
            ->assertSee('Add Faculty Account');
    }

    public function test_admin_can_edit_faculty_profile_and_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $account = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com', 'role' => 'faculty']);
        $faculty = Faculty::create(['user_id' => $account->id, 'name' => 'Old Name', 'designation' => 'Instructor']);

        $this->actingAs($admin)->put(route('admin.faculty.update', $faculty), [
            'name' => 'Updated Faculty',
            'employee_number' => 'FAC-100',
            'designation' => 'Senior Instructor',
            'email' => 'updated.faculty@example.com',
            'is_active' => '0',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('admin.faculty.index'))->assertSessionHasNoErrors();

        $faculty->refresh();
        $account->refresh();
        $this->assertSame('Updated Faculty', $faculty->name);
        $this->assertSame('Senior Instructor', $faculty->designation);
        $this->assertSame('Updated Faculty', $account->name);
        $this->assertSame('updated.faculty@example.com', $account->email);
        $this->assertFalse($account->is_active);
    }

    public function test_admin_can_edit_an_instructor_without_an_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $faculty = Faculty::create(['name' => 'List Instructor', 'designation' => 'Instructor']);

        $this->actingAs($admin)->put(route('admin.faculty.update', $faculty), [
            'name' => 'Renamed Instructor',
            'designation' => 'Course Facilitator',
        ])->assertRedirect(route('admin.faculty.index'))->assertSessionHasNoErrors();

        $this->assertSame('Renamed Instructor', $faculty->refresh()->name);
        $this->assertNull($faculty->user_id);
    }
}
