<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\ExcuseRequest;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
use App\Models\ReasonCategory;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlipVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_approved_slip_renders_verification_details(): void
    {
        $request = $this->createVerifiableRequest('approved', 'OLSHCO-2026-0001');

        $response = $this->get(route('verify', $request->reference_number));

        $response->assertOk()
            ->assertSee('VALID OFFICIAL SLIP')
            ->assertSee($request->reference_number)
            ->assertSee($request->student->user->name);
    }

    public function test_valid_acknowledged_and_completed_slips_render_verification_details(): void
    {
        $acknowledged = $this->createVerifiableRequest('acknowledged', 'OLSHCO-2026-0002');
        $this->get(route('verify', $acknowledged->reference_number))
            ->assertOk()
            ->assertSee('VALID OFFICIAL SLIP');

        $completed = $this->createVerifiableRequest('completed', 'OLSHCO-2026-0003');
        $this->get(route('verify', $completed->reference_number))
            ->assertOk()
            ->assertSee('VALID OFFICIAL SLIP');
    }

    public function test_existing_legacy_reference_numbers_remain_verifiable_without_conflict(): void
    {
        $legacyRequest = $this->createVerifiableRequest('approved', 'EAS-2026-CA-0059');

        $this->get(route('verify', 'EAS-2026-CA-0059'))
            ->assertOk()
            ->assertSee('VALID OFFICIAL SLIP')
            ->assertSee('EAS-2026-CA-0059');

        $uuidRequest = $this->createVerifiableRequest('approved', (string) \Illuminate\Support\Str::uuid());

        $this->get(route('verify', $uuidRequest->reference_number))
            ->assertOk()
            ->assertSee('VALID OFFICIAL SLIP')
            ->assertSee($uuidRequest->reference_number);
    }

    public function test_invalid_slip_reference_redirects_back_to_previous_page_with_error(): void
    {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $response = $this->actingAs($studentUser)
            ->from(route('requests.index'))
            ->get(route('verify', 'INVALID-NONEXISTENT-REF'));

        $response->assertRedirect(route('requests.index'))
            ->assertSessionHas('error');
    }

    public function test_unapproved_or_draft_slip_reference_redirects_back_with_error(): void
    {
        $draftRequest = $this->createVerifiableRequest('draft', 'DRAFT-REF-999');

        $response = $this->get(route('verify', $draftRequest->reference_number));

        $response->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    private function createVerifiableRequest(string $status, string $reference): ExcuseRequest
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::firstOrCreate(['code' => 'BSIT'], ['name' => 'BS Information Technology']);
        $section = Section::firstOrCreate(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $student = Student::create([
            'user_id' => $user->id,
            'student_number' => '2026-'.rand(1000, 9999),
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => 1,
        ]);
        $faculty = Faculty::firstOrCreate(['employee_number' => 'FAC-VERIFY-1'], ['name' => 'Dr. Verification Evaluator']);
        $subject = Subject::firstOrCreate(['code' => 'VER101'], ['name' => 'Verification Subject', 'course_id' => $course->id, 'year_level' => 1]);
        $academicYear = AcademicYear::firstOrCreate(['name' => '2026-2027'], ['is_current' => true]);
        $semester = Semester::firstOrCreate(['name' => 'First Semester'], ['is_current' => true]);
        $reason = ReasonCategory::firstOrCreate(['name' => 'Medical'], ['is_active' => true]);

        return ExcuseRequest::create([
            'reference_number' => $reference,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'facilitator_id' => $faculty->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'absence_date' => now()->subDay()->toDateString(),
            'reason_category_id' => $reason->id,
            'explanation' => 'Test verifiable excuse slip.',
            'status' => $status,
            'approved_at' => $status === 'approved' ? now() : null,
        ]);
    }
}
