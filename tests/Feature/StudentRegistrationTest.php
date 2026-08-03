<?php

namespace Tests\Feature;

use App\Exceptions\VirusScanException;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\VirusScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_form_is_required_for_student_self_registration(): void
    {
        $this->post(route('register.store'), $this->registrationData())
            ->assertSessionHasErrors('assessment_form');
    }

    public function test_student_can_register_with_an_assessment_form_and_admin_can_view_it(): void
    {
        Storage::fake('local');
        Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $form = UploadedFile::fake()->image('assessment.png', 800, 1000);

        $this->post(route('register.store'), $this->registrationData([
            'assessment_form' => $form,
        ]))->assertRedirect(route('login'));

        $student = Student::firstOrFail();
        $this->assertNull($student->user->registration_verified_at);
        $this->assertGuest();
        Storage::disk('local')->assertExists($student->assessment_form_path);
        $this->assertSame('assessment.png', $student->assessment_form_name);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)
            ->get(route('admin.students.assessment-form', $student))
            ->assertOk();
    }

    public function test_pending_student_cannot_login_until_an_admin_verifies_registration(): void
    {
        Storage::fake('local');
        Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);

        $this->post(route('register.store'), $this->registrationData([
            'assessment_form' => UploadedFile::fake()->image('assessment.png', 200, 200),
        ]));

        $student = Student::firstOrFail();
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

    public function test_assessment_form_with_embedded_php_is_rejected(): void
    {
        Storage::fake('local');
        $validImage = UploadedFile::fake()->image('source.png', 200, 200);
        $maliciousImage = UploadedFile::fake()->createWithContent(
            'assessment.png',
            file_get_contents($validImage->getRealPath()).'<?php system($_GET["cmd"]); ?>'
        );

        $this->post(route('register.store'), $this->registrationData([
            'assessment_form' => $maliciousImage,
        ]))->assertSessionHasErrors('assessment_form');

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

        $this->post(route('register.store'), $this->registrationData([
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
            'first_name' => 'Sample',
            'middle_name' => '',
            'last_name' => 'Student',
            'address' => 'Guimba, Nueva Ecija',
            'year_level' => 1,
            'block' => 'A',
            'email' => 'student@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ], $overrides);
    }
}
