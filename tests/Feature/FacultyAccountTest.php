<?php

namespace Tests\Feature;

use App\Mail\AdministratorAccountCreated;
use App\Models\Faculty;
use App\Models\Course;
use App\Models\InstructorAssignment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            'email' => 'old@example.com',
            'is_active' => '0',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('admin.faculty.index'))->assertSessionHasNoErrors();

        $faculty->refresh();
        $account->refresh();
        $this->assertSame('Updated Faculty', $faculty->name);
        $this->assertSame('Senior Instructor', $faculty->designation);
        $this->assertSame('Updated Faculty', $account->name);
        $this->assertSame('old@example.com', $account->email);
        $this->assertFalse($account->is_active);
    }

    public function test_admin_cannot_change_linked_faculty_email_through_faculty_edit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $account = User::factory()->create(['email' => 'faculty@example.com', 'role' => 'faculty']);
        $faculty = Faculty::create(['user_id' => $account->id, 'name' => 'Faculty Member', 'designation' => 'Instructor']);

        $this->actingAs($admin)->put(route('admin.faculty.update', $faculty), [
            'name' => 'Changed Name',
            'designation' => 'Instructor',
            'email' => 'replacement@example.com',
            'is_active' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertSame('faculty@example.com', $account->fresh()->email);
        $this->assertSame('Faculty Member', $faculty->fresh()->name);
        $this->actingAs($admin)->get(route('admin.faculty.edit', $faculty))
            ->assertOk()->assertSee('readonly', false);
    }

    public function test_admin_can_take_over_an_existing_faculty_profile_without_losing_assignments(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com', 'is_active' => true]);
        $oldAccount = User::factory()->create(['role' => 'faculty', 'email' => 'old-faculty@example.com', 'is_active' => true]);
        $faculty = Faculty::create(['user_id' => $oldAccount->id, 'name' => 'Existing Instructor', 'designation' => 'Instructor']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $subject = Subject::create(['code' => 'IT101', 'name' => 'Programming', 'course_id' => $course->id]);
        $assignment = InstructorAssignment::create([
            'faculty_id' => $faculty->id, 'course_id' => $course->id,
            'subject_id' => $subject->id, 'year_level' => 1, 'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.faculty.reassign-account', $faculty), [
            'admin_email' => $admin->email,
            'confirm_reassignment' => '1',
        ])->assertRedirect(route('admin.faculty.edit', $faculty))->assertSessionHasNoErrors();

        $this->assertSame($admin->id, $faculty->fresh()->user_id);
        $this->assertSame($faculty->id, $assignment->fresh()->faculty_id);
        $this->assertSame(['admin', 'faculty'], $admin->fresh()->assignedRoles());
        $this->assertFalse($oldAccount->fresh()->is_active);
        $this->assertSame('old-faculty@example.com', $oldAccount->email);
    }

    public function test_reassignment_rejects_an_admin_with_another_assigned_faculty_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com', 'is_active' => true]);
        $currentAccount = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $faculty = Faculty::create(['user_id' => $currentAccount->id, 'name' => 'Existing Instructor', 'designation' => 'Instructor']);
        $otherProfile = Faculty::create(['user_id' => $admin->id, 'name' => 'Other Instructor', 'designation' => 'Instructor']);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $subject = Subject::create(['code' => 'IT101', 'name' => 'Programming', 'course_id' => $course->id]);
        InstructorAssignment::create([
            'faculty_id' => $otherProfile->id, 'course_id' => $course->id,
            'subject_id' => $subject->id, 'year_level' => 1, 'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.faculty.reassign-account', $faculty), [
            'admin_email' => $admin->email,
            'confirm_reassignment' => '1',
        ])->assertSessionHasErrors('admin_email');

        $this->assertSame($currentAccount->id, $faculty->fresh()->user_id);
        $this->assertTrue($currentAccount->fresh()->is_active);
        $this->assertDatabaseHas('faculty', ['id' => $otherProfile->id, 'user_id' => $admin->id]);
    }

    public function test_reassignment_replaces_an_empty_admin_faculty_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin', 'faculty'], 'email' => 'admin@example.com', 'is_active' => true]);
        $emptyProfile = Faculty::create(['user_id' => $admin->id, 'name' => 'Unused Profile', 'designation' => 'Instructor']);
        $oldAccount = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $assignedProfile = Faculty::create(['user_id' => $oldAccount->id, 'name' => 'Assigned Instructor', 'designation' => 'Instructor']);

        $this->actingAs($admin)->post(route('admin.faculty.reassign-account', $assignedProfile), [
            'admin_email' => $admin->email,
            'confirm_reassignment' => '1',
        ])->assertRedirect(route('admin.faculty.edit', $assignedProfile))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('faculty', ['id' => $emptyProfile->id]);
        $this->assertSame($admin->id, $assignedProfile->fresh()->user_id);
    }

    public function test_faculty_password_edit_revokes_other_sessions_and_remember_logins(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $account = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $faculty = Faculty::create(['user_id' => $account->id, 'name' => $account->name, 'designation' => 'Instructor']);
        $oldToken = $account->getRememberToken();
        DB::table('sessions')->insert([
            'id' => 'faculty-old-session',
            'user_id' => $account->id,
            'payload' => base64_encode('session'),
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)->put(route('admin.faculty.update', $faculty), [
            'name' => $faculty->name,
            'designation' => $faculty->designation,
            'email' => $account->email,
            'is_active' => '1',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('admin.faculty.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'faculty-old-session']);
        $this->assertNotSame($oldToken, $account->fresh()->getRememberToken());
        $this->assertTrue(Hash::check('new-password', $account->fresh()->password));
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
