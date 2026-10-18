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
use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRequestReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_rechecks_status_when_given_a_stale_request_model(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id, 'student_number' => '2026-0999',
            'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1,
        ]);
        $faculty = Faculty::create(['name' => 'Maria Reyes']);
        $subject = Subject::create(['code' => 'TEST101', 'name' => 'Test Subject', 'course_id' => $course->id]);
        $year = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);
        $request = ExcuseRequest::create([
            'student_id' => $student->id, 'subject_id' => $subject->id,
            'facilitator_id' => $faculty->id, 'academic_year_id' => $year->id,
            'semester_id' => $semester->id, 'reason_category_id' => $reason->id,
            'absence_date' => today(), 'explanation' => 'Test request.', 'status' => 'submitted',
        ]);

        $this->actingAs($admin);
        app(RequestWorkflowService::class)->transition($request, 'under_review');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(RequestWorkflowService::class)->transition($request, 'under_review');
    }

    public function test_system_admin_can_review_and_approve_a_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Antolin Catabona', 'role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        Section::create(['course_id' => $course->id, 'name' => 'B', 'year_level' => 2]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '2026-0100',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => 1,
        ]);
        $faculty = Faculty::create(['employee_number' => 'FAC-500', 'name' => 'Maria Reyes']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming Fundamentals', 'course_id' => $course->id, 'year_level' => 1]);
        $academicYear = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);
        $request = ExcuseRequest::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'facilitator_id' => $faculty->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'absence_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:30',
            'reason_category_id' => $reason->id,
            'explanation' => 'The student was unable to attend because of illness.',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('requests.review', $request), ['decision' => 'under_review'])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('requests.index', ['year_level' => 1]))
            ->assertOk()
            ->assertSee('All Years')
            ->assertSee('Year 1')
            ->assertSee('Year 2')
            ->assertSee('Antolin Catabona')
            ->assertViewHas('selectedYearLevel', 1)
            ->assertViewHas('pendingCountTotal', 1)
            ->assertViewHas('pendingCountsByYear', fn ($counts) => (int) $counts->get(1) === 1);
        $this->actingAs($admin)->get(route('requests.index', ['year_level' => 2]))
            ->assertOk()
            ->assertDontSee('Antolin Catabona');

        $this->actingAs($admin)->put(route('requests.settings.update'), [
            'program_head_name' => 'Dr. Original Issuer',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('requests.review', $request->fresh()), ['decision' => 'approved', 'slip_remark' => 'EXCUSED'])
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame($admin->id, $request->reviewed_by);
        $this->assertSame('Dr. Original Issuer', $request->slip_issuer_name);
        $this->assertSame('captured', $request->slipIssuance->snapshot['snapshot_source']);
        $this->assertTrue(Str::isUuid($request->reference_number));
        $this->actingAs($admin)->put(route('requests.settings.update'), [
            'program_head_name' => 'Dr. New Issuer',
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('Approved by '.$admin->name);
        $this->actingAs($studentUser)
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertDontSee('Approved by '.$admin->name);
        $this->actingAs($admin)
            ->get(route('requests.slip', $request))
            ->assertOk()
            ->assertSee('SCAN TO VERIFY')
            ->assertSee('To the following instructors of:')
            ->assertSee('DR. ORIGINAL ISSUER')
            ->assertDontSee('DR. NEW ISSUER')
            ->assertSee('data:image/svg+xml;base64,', false);

        $issuedAt = $request->approved_at;
        $reference = $request->reference_number;
        $this->actingAs($admin)->post(route('requests.review', $request), [
            'decision' => 'approved',
            'slip_remark' => 'CONDITIONAL',
            'remarks' => 'Amendment after issuance.',
        ])->assertSessionHasNoErrors();
        $request->refresh();
        $this->assertTrue($request->approved_at->equalTo($issuedAt));
        $this->assertSame($reference, $request->reference_number);
        $this->assertSame('Dr. Original Issuer', $request->slip_issuer_name);
        $this->assertSame('EXCUSED', $request->slip_remark);
        $this->assertDatabaseHas('slip_amendments', [
            'excuse_request_id' => $request->id,
            'slip_remark' => 'CONDITIONAL',
            'remarks' => 'Amendment after issuance.',
        ]);
        $this->actingAs($admin)->get(route('requests.slip', $request))
            ->assertOk()->assertSee('DR. ORIGINAL ISSUER')->assertDontSee('DR. NEW ISSUER')
            ->assertSee('EXCUSED')->assertSee('AMENDED: Current official result is CONDITIONAL.')
            ->assertSee('Amendment after issuance.');
        $studentUser->update(['name' => 'Changed Student Name']);
        $subject->update(['code' => 'NEW101']);
        $this->actingAs($admin)->get(route('requests.slip', $request))
            ->assertOk()->assertSee('Antolin Catabona')->assertSee('PF101')
            ->assertDontSee('Changed Student Name')->assertDontSee('NEW101');
        $this->get(route('verify', $reference))
            ->assertOk()->assertSee('AMENDED OFFICIAL SLIP')
            ->assertSee('Original issued result')->assertSee('Current official result')
            ->assertSee('CONDITIONAL')->assertSee('Amendment after issuance.')
            ->assertSee('Antolin Catabona')->assertSee('PF101')
            ->assertDontSee('Changed Student Name')->assertDontSee('NEW101');

        $this->actingAs($admin)->post(route('requests.review', $request), [
            'decision' => 'returned', 'remarks' => 'Change the subject.',
        ])->assertSessionHasErrors('status');
        $this->assertSame('approved', $request->fresh()->status);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Late excuse submissions')
            ->assertSee('Excused Students')
            ->assertSee('Student Excuse Analytics')
            ->assertViewHas('lateStudents', 0)
            ->assertViewHas('excusedStudents', 0)
            ->assertViewHas('analytics', fn ($analytics) => $analytics->count() === 6)
            ->assertViewHas('analyticsByPeriod', fn ($analytics) =>
                $analytics['day']->count() === 7
                && $analytics['day']->last()['excused'] === 0
                && $analytics['week']->count() === 6
                && $analytics['month']->count() === 6
            );

        $this->actingAs($admin)
            ->post(route('requests.review', $request), [
                'decision' => 'rejected',
                'remarks' => 'Approval was reversed after further review.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $request->fresh()->status);
        $this->get(route('verify', $reference))
            ->assertOk()->assertSee('REVOKED OFFICIAL SLIP')
            ->assertSee('Current official result')->assertSee('REVOKED');
        $this->actingAs($admin)->post(route('requests.review', $request), [
            'decision' => 'approved', 'slip_remark' => 'EXCUSED', 'remarks' => 'Reapprove.',
        ])->assertSessionHasErrors('status');
        InstructorAssignment::create([
            'faculty_id' => $faculty->id, 'course_id' => $course->id,
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'year_level' => 1, 'is_active' => true,
        ]);
        $this->actingAs($studentUser)->post(route('requests.store'), [
            'absence_date' => $request->absence_date->toDateString(),
            'subject_ids' => [$subject->id],
            'reason_category_id' => $reason->id,
            'explanation' => 'A corrected request after the original slip was revoked.',
            'intent' => 'draft',
            'declaration' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, ExcuseRequest::where('student_id', $student->id)->count());
        $this->assertDatabaseHas('request_status_histories', [
            'excuse_request_id' => $request->id,
            'previous_status' => 'approved',
            'new_status' => 'rejected',
        ]);
        $this->actingAs($admin)->delete(route('requests.destroy', $request))
            ->assertSessionHasNoErrors();
        $this->get(route('verify', $reference))
            ->assertOk()->assertSee('REVOKED OFFICIAL SLIP')
            ->assertSee('Amendment after issuance.');
    }

    public function test_official_remarks_are_printed_on_an_approved_slip(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '2026-0103',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => 1,
        ]);
        $faculty = Faculty::create(['employee_number' => 'FAC-503', 'name' => 'Maria Reyes']);
        $subject = Subject::create(['code' => 'GE1', 'name' => 'Understanding the Self', 'course_id' => $course->id, 'year_level' => 1]);
        $academicYear = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Other', 'is_active' => true]);
        $request = ExcuseRequest::create([
            'reference_number' => 'EAS-2026-0103',
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'facilitator_id' => $faculty->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'absence_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:30',
            'reason_category_id' => $reason->id,
            'explanation' => 'The student was unable to attend class.',
            'status' => 'approved',
            'slip_remark' => 'CONDITIONAL',
            'official_remarks' => 'Under Constant Monitoring',
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('requests.settings.update'), [
                'program_head_name' => 'Dr. Maria Santos, MIT',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'program_head_name',
            'value' => 'Dr. Maria Santos, MIT',
        ]);

        $this->actingAs($admin)
            ->get(route('requests.slip', $request))
            ->assertOk()
            ->assertSee('CONDITIONAL')
            ->assertSee('Under Constant Monitoring')
            ->assertSee('DR. MARIA SANTOS, MIT')
            ->assertSee('Request time:')
            ->assertSee('8:00 AM')
            ->assertDontSee('8:00 AM to 9:30 AM')
            ->assertSee('GE1')
            ->assertDontSee('Understanding the Self');
    }

    public function test_system_admin_can_return_and_reject_requests_with_remarks(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '2026-0101',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => 1,
        ]);
        $faculty = Faculty::create(['employee_number' => 'FAC-501', 'name' => 'Maria Reyes']);
        $subject = Subject::create(['code' => 'PF102', 'name' => 'Programming Fundamentals', 'course_id' => $course->id, 'year_level' => 1]);
        $academicYear = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);
        $attributes = [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'facilitator_id' => $faculty->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'absence_date' => now()->toDateString(),
            'reason_category_id' => $reason->id,
            'explanation' => 'The student was unable to attend because of illness.',
            'status' => 'under_review',
            'submitted_at' => now(),
        ];
        $returnedRequest = ExcuseRequest::create($attributes);
        $rejectedRequest = ExcuseRequest::create($attributes);

        $this->actingAs($admin)
            ->post(route('requests.review', $returnedRequest), [
                'decision' => 'returned',
                'remarks' => 'Please provide a supporting document.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('requests.review', $rejectedRequest), [
                'decision' => 'rejected',
                'remarks' => 'The submitted reason is not valid.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('returned', $returnedRequest->fresh()->status);
        $this->assertSame('Please provide a supporting document.', $returnedRequest->fresh()->official_remarks);
        $this->assertSame('rejected', $rejectedRequest->fresh()->status);
        $this->assertSame('The submitted reason is not valid.', $rejectedRequest->fresh()->official_remarks);
    }

    public function test_program_head_can_reverse_a_rejected_request_to_approved(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_number' => '2026-0102',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => 1,
        ]);
        $faculty = Faculty::create(['employee_number' => 'FAC-502', 'name' => 'Maria Reyes']);
        $subject = Subject::create(['code' => 'PF103', 'name' => 'Programming Fundamentals', 'course_id' => $course->id, 'year_level' => 1]);
        $academicYear = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);
        $request = ExcuseRequest::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'facilitator_id' => $faculty->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'absence_date' => now()->toDateString(),
            'reason_category_id' => $reason->id,
            'explanation' => 'The student was unable to attend because of illness.',
            'status' => 'rejected',
            'submitted_at' => now(),
            'rejected_at' => now(),
            'official_remarks' => 'Initially denied.',
        ]);

        $this->actingAs($programHead)
            ->post(route('requests.review', $request), [
                'decision' => 'approved',
                'slip_remark' => 'UNEXCUSED',
                'remarks' => 'Supporting document was verified.',
            ])
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame('UNEXCUSED', $request->slip_remark);
        $this->assertSame($programHead->id, $request->reviewed_by);
        $this->assertNotNull($request->reference_number);
        $this->assertDatabaseHas('request_status_histories', [
            'excuse_request_id' => $request->id,
            'previous_status' => 'rejected',
            'new_status' => 'approved',
            'action_by' => $programHead->id,
        ]);
    }
}
