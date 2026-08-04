<?php

namespace Tests\Feature;

use App\Exceptions\VirusScanException;
use App\Mail\StudentRegistrationOtp;
use App\Models\Course;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\VirusScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_form_is_required_for_student_self_registration(): void
    {
        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData())
            ->assertSessionHasErrors('assessment_form');
    }

    public function test_student_can_register_with_an_assessment_form_and_admin_can_view_it(): void
    {
        Mail::fake();
        Storage::fake('local');
        Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $form = UploadedFile::fake()->image('assessment.png', 800, 1000);

        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
            'assessment_form' => $form,
        ]))->assertRedirect(route('login'));

        $student = Student::firstOrFail();
        $this->assertNull($student->user->registration_verified_at);
        $this->assertGuest();
        Storage::disk('local')->assertExists($student->assessment_form_path);
        $this->assertSame('assessment.png', $student->assessment_form_name);
        $this->assertNotNull($student->user->email_verified_at);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)
            ->get(route('admin.students.assessment-form', $student))
            ->assertOk();
    }

    public function test_pending_student_cannot_login_until_an_admin_verifies_registration(): void
    {
        Mail::fake();
        Storage::fake('local');
        Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);

        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
            'assessment_form' => UploadedFile::fake()->image('assessment.png', 200, 200),
        ]));

        $student = Student::firstOrFail();
        $this->assertNotNull($student->user->fresh()->email_verified_at);

        $this->post(route('login.attempt'), ['email' => 'student@example.com', 'password' => 'secure-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->patch(route('admin.students.verify', $student))
            ->assertSessionHas('success');

        auth()->logout();
        $this->post(route('login.attempt'), ['email' => 'student@example.com', 'password' => 'secure-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($student->user->fresh());
    }

    public function test_student_can_request_an_otp_for_the_email_field(): void
    {
        Mail::fake();

        $this->postJson(route('register.send-otp'), ['email' => 'student@example.com'])
            ->assertOk()
            ->assertJson(['message' => 'A six-digit OTP was sent to your email.']);

        Mail::assertSent(StudentRegistrationOtp::class, fn ($mail) =>
            $mail->hasTo('student@example.com') && strlen($mail->otp) === 6
        );
    }

    public function test_irregular_student_selects_current_subjects_from_multiple_year_levels(): void
    {
        Mail::fake();
        Storage::fake('local');
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $firstYear = Subject::create(['code' => 'CC101', 'name' => 'Introduction to Computing', 'course_id' => $course->id, 'year_level' => 1, 'is_active' => true]);
        $thirdYear = Subject::create(['code' => 'NET102', 'name' => 'Networking 2', 'course_id' => $course->id, 'year_level' => 3, 'is_active' => true]);
        Subject::create(['code' => 'GE8', 'name' => 'Art Appreciation', 'course_id' => $course->id, 'year_level' => 2, 'is_active' => true]);

        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
            'student_type' => 'irregular',
            'subject_ids' => [$firstYear->id, $thirdYear->id],
            'assessment_form' => UploadedFile::fake()->image('assessment.png', 200, 200),
        ]))->assertRedirect(route('login'));

        $student = Student::firstOrFail();
        $this->assertSame('irregular', $student->student_type);
        $this->assertEqualsCanonicalizing(
            [$firstYear->id, $thirdYear->id],
            $student->subjects()->pluck('subjects.id')->all()
        );
    }

    public function test_registration_rejects_an_incorrect_or_expired_otp(): void
    {
        $data = $this->registrationData(['assessment_form' => UploadedFile::fake()->image('assessment.png', 200, 200)]);

        $this->withSession($this->otpSession())->post(route('register.store'), array_merge($data, ['otp' => '000000']))
            ->assertSessionHasErrors(['otp' => 'The OTP is incorrect.']);

        $expired = $this->otpSession();
        $expired['registration_email_otp_expires_at'] = now()->subMinute()->timestamp;
        $this->withSession($expired)->post(route('register.store'), $data)
            ->assertSessionHasErrors(['otp' => 'The OTP has expired. Send a new code.']);
    }

    public function test_assessment_form_with_php_code_is_rejected(): void
    {
        Storage::fake('local');
        $validImage = UploadedFile::fake()->image('source.png', 200, 200);
        $maliciousImage = UploadedFile::fake()->createWithContent(
            'assessment.png',
            file_get_contents($validImage->getRealPath()).'<?php system($_GET["cmd"]); ?>'
        );

        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
            'assessment_form' => $maliciousImage,
        ]))->assertSessionHasErrors([
            'assessment_form' => 'The assessment form contains prohibited PHP code.',
        ]);

        Storage::disk('local')->assertDirectoryEmpty('student-assessment-forms');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_registration_is_rejected_when_antivirus_detects_malware(): void
    {
        Storage::fake('local');
        $this->mock(VirusScanner::class)
            ->shouldReceive('scan')
            ->once()
            ->andThrow(new VirusScanException('Test malware signature detected.', true));

        $this->withSession($this->otpSession())->post(route('register.store'), $this->registrationData([
            'assessment_form' => UploadedFile::fake()->image('assessment.png', 200, 200),
        ]))->assertSessionHasErrors([
            'assessment_form' => 'The assessment form was rejected because malware was detected.',
        ]);

        Storage::disk('local')->assertDirectoryEmpty('student-assessment-forms');
        $this->assertDatabaseCount('students', 0);
    }

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'student_number' => '2026-1001',
            'student_type' => 'regular',
            'first_name' => 'Sample',
            'middle_name' => '',
            'last_name' => 'Student',
            'address' => 'Guimba, Nueva Ecija',
            'year_level' => 1,
            'block' => 'A',
            'email' => 'student@example.com',
            'otp' => '123456',
            'password' => 'secure-password',
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
