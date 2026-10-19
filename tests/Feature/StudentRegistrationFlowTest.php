<?php

namespace Tests\Feature;

use App\Mail\StudentRegistrationOtp;
use App\Mail\StudentEmailVerificationCode;
use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_registration_requires_an_assessment_form_and_a_valid_email_code(): void
    {
        $data = $this->registrationData();
        $this->withSession($this->otpSession())->post(route('register.store'), $data)
            ->assertSessionHasErrors('assessment_form');

        $data['assessment_form'] = UploadedFile::fake()->image('assessment.png', 200, 200);
        $this->withSession($this->otpSession())->post(route('register.store'), array_merge($data, ['otp' => '000000']))
            ->assertSessionHasErrors('otp');
        $expired = $this->otpSession();
        $expired['registration_email_otp_expires_at'] = now()->subMinute()->timestamp;
        $this->withSession($expired)->post(route('register.store'), $data)
            ->assertSessionHasErrors('otp');
    }

    public function test_student_can_request_an_email_code(): void
    {
        Mail::fake();
        $this->postJson(route('register.send-otp'), ['email' => 'student@example.com'])->assertOk();
        Mail::assertSent(StudentRegistrationOtp::class, fn ($mail) => $mail->hasTo('student@example.com')
            && strlen($mail->otp) === 6);
    }

    public function test_verification_resend_limit_returns_to_the_form_with_a_retry_message(): void
    {
        Mail::fake();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $url = route('register.verify-email.resend');

        $this->withSession(['email_verification_user_id' => $student->id])->post($url)->assertRedirect();
        $this->post($url)->assertRedirect();
        $this->post($url)
            ->assertRedirect(route('register.verify-email'))
            ->assertSessionHas('error')
            ->assertHeader('Retry-After');

        Mail::assertSent(StudentEmailVerificationCode::class, 2);
    }

    public function test_verification_resend_limit_is_separate_for_students_on_the_same_ip(): void
    {
        Mail::fake();
        $first = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $second = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $url = route('register.verify-email.resend');

        $this->withSession(['email_verification_user_id' => $first->id])->post($url)->assertRedirect();
        $this->post($url)->assertRedirect();
        $this->withSession(['email_verification_user_id' => $second->id])->post($url)
            ->assertRedirect()->assertSessionHas('success');

        Mail::assertSent(StudentEmailVerificationCode::class, 3);
    }

    public function test_self_registered_student_waits_for_admin_approval(): void
    {
        Storage::fake('local');
        Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
            'assessment_form' => UploadedFile::fake()->image('assessment.png', 200, 200),
        ]))->assertRedirect(route('login'));

        $student = Student::firstOrFail();
        Storage::disk('local')->assertExists($student->assessment_form_path);
        $this->assertNull($student->user->registration_verified_at);
        $this->assertNotNull($student->user->email_verified_at);
        $this->post(route('login.attempt'), ['email' => 'student@example.com', 'password' => 'secure-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('admin.students.assessment-form', $student))->assertOk();
        $this->patch(route('admin.students.verify', $student))->assertSessionHas('success');
        auth()->logout();
        $this->post(route('login.attempt'), ['email' => 'student@example.com', 'password' => 'secure-password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_irregular_student_can_choose_subjects_from_multiple_years(): void
    {
        Storage::fake('local');
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $first = Subject::create(['code' => 'CC101', 'name' => 'Computing', 'course_id' => $course->id, 'year_level' => 1]);
        $third = Subject::create(['code' => 'NET301', 'name' => 'Networking', 'course_id' => $course->id, 'year_level' => 3]);

        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
            'student_type' => 'irregular', 'subject_ids' => [$first->id, $third->id],
            'assessment_form' => UploadedFile::fake()->image('assessment.png', 200, 200),
        ]))->assertRedirect(route('login'));

        $this->assertEqualsCanonicalizing([$first->id, $third->id],
            Student::firstOrFail()->subjects()->pluck('subjects.id')->all());
    }

    public function test_registration_cannot_join_an_inactive_section(): void
    {
        Storage::fake('local');
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1, 'is_active' => false]);

        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
            'assessment_form' => UploadedFile::fake()->image('assessment.png', 200, 200),
        ]))->assertSessionHasErrors('block');

        $this->assertDatabaseCount('students', 0);
        Storage::disk('local')->assertDirectoryEmpty('student-assessment-forms');
    }

    public function test_registration_rejects_an_image_containing_php_without_creating_an_account(): void
    {
        Storage::fake('local');
        $image = UploadedFile::fake()->image('assessment.png', 200, 200);
        file_put_contents($image->getRealPath(), '<?php echo "unsafe"; ?>', FILE_APPEND);
        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
            'assessment_form' => $image,
        ]))->assertSessionHasErrors('assessment_form');

        $this->assertDatabaseCount('students', 0);
        Storage::disk('local')->assertDirectoryEmpty('student-assessment-forms');
    }

    public function test_registration_rejects_images_over_the_resolution_limit(): void
    {
        Storage::fake('local');

        foreach ([[4001, 100], [4000, 2001]] as [$width, $height]) {
            $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
                'assessment_form' => UploadedFile::fake()->image('assessment.png', $width, $height),
            ]))->assertSessionHasErrors('assessment_form');
        }

        $this->assertDatabaseCount('students', 0);
        Storage::disk('local')->assertDirectoryEmpty('student-assessment-forms');
    }

    public function test_registration_rejects_non_file_assessment_input_without_a_server_error(): void
    {
        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
            'assessment_form' => 'not-an-upload',
        ]))->assertSessionHasErrors('assessment_form');

        $this->assertDatabaseCount('students', 0);
    }

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'student_number' => '2026-1001', 'student_type' => 'regular',
            'first_name' => 'Sample', 'last_name' => 'Student', 'address' => 'Nueva Ecija',
            'year_level' => 1, 'block' => 'A', 'email' => 'student@example.com',
            'otp' => '123456', 'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ], $overrides);
    }

    private function otpSession(): array
    {
        return [
            'registration_email_otp_hash' => hash('sha256', '123456'),
            'registration_email_otp_email' => 'student@example.com',
            'registration_email_otp_expires_at' => now()->addMinutes(10)->timestamp,
        ];
    }
}
