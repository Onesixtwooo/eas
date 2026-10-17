<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\AccountArchive;
use App\Models\AcademicYear;
use App\Models\ExcuseRequest;
use App\Models\Faculty;
use App\Models\ReasonCategory;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Mail\StudentEmailVerificationCode;
use App\Mail\StudentRegistrationDeclined;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminStudentIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_declined_registration_cannot_be_verified_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create([
            'role' => 'student', 'is_active' => false,
            'registration_verified_at' => null,
            'registration_declined_at' => now(),
            'registration_decline_reason' => 'Missing document',
            'email_verified_at' => now(),
        ]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $profile = Student::create(['user_id' => $student->id, 'student_number' => '26-2998',
            'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1]);

        $this->actingAs($admin)->patch(route('admin.students.verify', $profile))
            ->assertSessionHas('error', 'A declined registration cannot be verified. Archive the account if it still exists.');
        $this->get(route('admin.students.show', $profile))
            ->assertOk()->assertSee('Archive & Delete Declined Account', false)->assertDontSee('Verify Student Registration');
        $this->patch(route('admin.students.status', $profile))
            ->assertSessionHas('error', 'A declined registration cannot be activated. Archive the account.');

        $student->refresh();
        $this->assertFalse($student->is_active);
        $this->assertNull($student->registration_verified_at);
        $this->assertNotNull($student->registration_declined_at);
        $this->assertSame('Missing document', $student->registration_decline_reason);
    }

    public function test_declining_requires_confirmation_then_archives_and_deletes_the_account(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true,
            'registration_verified_at' => null, 'email_verified_at' => now()]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $profile = Student::create(['user_id' => $student->id, 'student_number' => '26-2997',
            'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1]);

        $this->actingAs($admin)->patch(route('admin.students.decline', $profile), [
            'reason' => 'Missing assessment form',
        ])->assertSessionHasErrors('confirm_delete');
        $this->get(route('admin.students.show', $profile))
            ->assertOk()->assertSee('I confirm this registration should be declined and the account deleted.');
        $this->assertDatabaseHas('users', ['id' => $student->id]);

        $this->patch(route('admin.students.decline', $profile), [
            'reason' => 'Missing assessment form', 'confirm_delete' => '1',
        ])->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $archive = AccountArchive::where('original_user_id', $student->id)->firstOrFail();
        $this->assertSame('deleted', $archive->outcome);
        $this->assertSame('Missing assessment form', $archive->records['user']['registration_decline_reason']);
        $this->assertNotNull($archive->records['user']['registration_declined_at']);
        Mail::assertSent(StudentRegistrationDeclined::class, fn ($mail) => $mail->hasTo($student->email));
    }

    public function test_admin_created_student_receives_a_code_and_can_verify_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'student_number' => '26-2999',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'address' => 'Manila',
            'year_level' => 1,
            'block' => 'A',
            'email' => 'ana@example.com',
            'password' => 'temporary-password',
            'password_confirmation' => 'temporary-password',
        ])->assertRedirect(route('admin.students.index'))->assertSessionHasNoErrors();

        $student = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertNotNull($student->registration_verified_at);
        $this->assertNull($student->email_verified_at);
        $otp = null;
        Mail::assertSent(StudentEmailVerificationCode::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return $mail->hasTo('ana@example.com');
        });
        $this->assertNotNull($otp);

        $this->post(route('logout'));
        $this->post(route('login.attempt'), [
            'email' => 'ana@example.com',
            'password' => 'temporary-password',
        ])->assertRedirect(route('register.verify-email'));
        $this->post(route('register.verify-email.store'), ['otp' => $otp])
            ->assertRedirect(route('login'));

        $this->assertNotNull($student->fresh()->email_verified_at);
    }

    public function test_admin_cannot_create_a_student_in_an_inactive_section(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1, 'is_active' => false]);

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'student_number' => '26-2996', 'first_name' => 'Ana', 'last_name' => 'Santos',
            'address' => 'Manila', 'year_level' => 1, 'block' => 'A',
            'email' => 'ana@example.com', 'password' => 'temporary-password',
            'password_confirmation' => 'temporary-password',
        ])->assertSessionHasErrors('block');

        $this->assertDatabaseCount('students', 0);
        Mail::assertNothingSent();
    }

    public function test_admin_can_bulk_deactivate_selected_students_without_affecting_others(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $students = collect([true, false, true])->map(function ($active, $index) use ($course, $section) {
            $user = User::factory()->create(['role' => 'student', 'is_active' => $active]);

            return Student::create([
                'user_id' => $user->id,
                'student_number' => '26-200'.$index,
                'course_id' => $course->id,
                'section_id' => $section->id,
                'year_level' => 1,
            ]);
        });

        $this->actingAs($admin)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('Deactivate Selected')
            ->assertSee('action="'.route('admin.students.bulk-deactivate').'"', false);

        $this->post(route('admin.students.bulk-deactivate'), [
            'student_ids' => [$students[0]->id, $students[1]->id],
        ])->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('success', '1 student account(s) deactivated.');

        $this->assertDatabaseHas('users', ['id' => $students[0]->user_id, 'is_active' => false]);
        $this->assertDatabaseHas('users', ['id' => $students[1]->user_id, 'is_active' => false]);
        $this->assertDatabaseHas('users', ['id' => $students[2]->user_id, 'is_active' => true]);
        $this->assertSame(3, Student::count());
    }

    public function test_bulk_deactivation_requires_selected_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.students.bulk-deactivate'))
            ->assertSessionHasErrors('student_ids');
    }

    public function test_bulk_delete_still_accepts_the_shared_forms_delete_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '26-2099',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.students.bulk-destroy'), [
                '_method' => 'DELETE',
                'student_ids' => [$student->id],
            ])
            ->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
    }

    public function test_section_filter_only_lists_sections_used_by_saved_students(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $studentUser = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        $course = Course::create([
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
        ]);
        $savedSection = Section::create([
            'course_id' => $course->id,
            'name' => 'A',
            'year_level' => 5,
            'is_active' => true,
        ]);
        $unusedSection = Section::create([
            'course_id' => $course->id,
            'name' => 'F',
            'year_level' => 2,
            'is_active' => true,
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '26-0003',
            'course_id' => $course->id,
            'section_id' => $savedSection->id,
            'year_level' => 5,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.students.index'));

        $response->assertOk();
        $response->assertViewHas('sections', function ($sections) use ($savedSection, $unusedSection) {
            return $sections->pluck('id')->all() === [$savedSection->id]
                && ! $sections->pluck('id')->contains($unusedSection->id);
        });
    }

    public function test_admin_can_delete_a_student_who_created_request_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '26-0004',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => 1,
        ]);
        $faculty = Faculty::create(['employee_number' => 'FAC-504', 'name' => 'Maria Reyes']);
        $subject = Subject::create([
            'code' => 'PF104',
            'name' => 'Programming Fundamentals',
            'course_id' => $course->id,
            'year_level' => 1,
        ]);
        $academicYear = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);
        $excuseRequest = ExcuseRequest::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'facilitator_id' => $faculty->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'absence_date' => now()->toDateString(),
            'reason_category_id' => $reason->id,
            'explanation' => 'The student was unable to attend because of illness.',
            'status' => 'draft',
        ]);
        $excuseRequest->histories()->create([
            'new_status' => 'draft',
            'action_by' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('excuse_requests', ['id' => $excuseRequest->id]);
    }

    public function test_admin_can_update_only_a_students_year_level_and_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $oldSection = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 2]);
        $newSection = Section::create(['course_id' => $course->id, 'name' => 'B', 'year_level' => 3]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '26-1001',
            'course_id' => $course->id,
            'section_id' => $oldSection->id,
            'year_level' => 2,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.students.academic-placement.update', $student), [
                'year_level' => 3,
                'section_id' => $newSection->id,
            ])
            ->assertSessionHas('success');

        $student->refresh();
        $this->assertSame(3, $student->year_level);
        $this->assertSame($newSection->id, $student->section_id);
        $this->assertSame($course->id, $student->course_id);
        $this->assertSame('26-1001', $student->student_number);
    }

    public function test_admin_cannot_assign_a_section_from_another_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $otherCourse = Course::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        $currentSection = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 2]);
        $invalidSection = Section::create(['course_id' => $otherCourse->id, 'name' => 'B', 'year_level' => 3]);
        $student = Student::create(['user_id' => $studentUser->id, 'student_number' => '26-1002', 'course_id' => $course->id, 'section_id' => $currentSection->id, 'year_level' => 2]);

        $this->actingAs($admin)
            ->patch(route('admin.students.academic-placement.update', $student), ['year_level' => 3, 'section_id' => $invalidSection->id])
            ->assertSessionHasErrors('section_id');

        $this->assertSame($currentSection->id, $student->fresh()->section_id);
    }

    public function test_student_details_include_a_message_button_for_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '26-1003',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee('Message')
            ->assertSee('action="'.route('messages.select', $student).'"', false);
    }
}
