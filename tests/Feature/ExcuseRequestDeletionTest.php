<?php

namespace Tests\Feature;

use App\Models\{AcademicYear, Course, ExcuseRequest, Faculty, InstructorAssignment, ReasonCategory, Section, Semester, Student, Subject, SupportingDocument, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExcuseRequestDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_a_request_and_its_attachment(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        [$request] = $this->makeRequest();
        Storage::disk('local')->put('supporting-documents/proof.pdf', 'proof');
        SupportingDocument::create(['excuse_request_id' => $request->id, 'disk' => 'local', 'path' => 'supporting-documents/proof.pdf', 'original_name' => 'proof.pdf', 'mime_type' => 'application/pdf', 'size' => 5]);

        $this->actingAs($admin)->delete(route('requests.destroy', $request))
            ->assertRedirect(route('requests.index'))->assertSessionHas('success');

        $this->assertDatabaseMissing('excuse_requests', ['id' => $request->id]);
        $this->assertDatabaseMissing('supporting_documents', ['excuse_request_id' => $request->id]);
        Storage::disk('local')->assertMissing('supporting-documents/proof.pdf');
    }

    public function test_student_cannot_delete_a_request(): void
    {
        [$request, $studentUser] = $this->makeRequest();

        $this->actingAs($studentUser)->delete(route('requests.destroy', $request))->assertForbidden();
        $this->assertDatabaseHas('excuse_requests', ['id' => $request->id]);
    }

    public function test_student_is_redirected_to_requests_instead_of_seeing_a_404(): void
    {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($studentUser)->get('/requests/999999')
            ->assertRedirect(route('requests.index'))
            ->assertSessionHas('error', 'The page or request you were looking for could not be found.');

        $this->actingAs($studentUser)->get('/a-page-that-does-not-exist')
            ->assertRedirect(route('requests.index'));
    }

    public function test_student_cannot_create_a_duplicate_request_for_the_same_subject_and_date(): void
    {
        [$request, $studentUser] = $this->makeRequest();

        $this->actingAs($studentUser)->post(route('requests.store'), [
            'absence_date' => $request->absence_date->toDateString(),
            'subject_id' => $request->subject_id,
            'reason_category_id' => $request->reason_category_id,
            'explanation' => 'This repeated submission must not create another request.',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'declaration' => '1',
            'intent' => 'submit',
        ])->assertRedirect(route('requests.show', $request))->assertSessionHas('error');

        $this->assertSame(1, ExcuseRequest::where('student_id', $request->student_id)
            ->where('subject_id', $request->subject_id)
            ->whereDate('absence_date', $request->absence_date)
            ->count());
    }

    public function test_student_can_cancel_a_submitted_request_and_create_a_replacement(): void
    {
        [$request, $studentUser] = $this->makeRequest();
        $request->update(['status' => 'submitted', 'submitted_at' => now()]);

        $this->actingAs($studentUser)->post(route('requests.cancel', $request))
            ->assertRedirect(route('requests.index'))->assertSessionHas('success');

        $this->assertSame('cancelled', $request->fresh()->status);
        $this->assertDatabaseHas('request_status_histories', [
            'excuse_request_id' => $request->id,
            'previous_status' => 'submitted',
            'new_status' => 'cancelled',
            'action_by' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)->post(route('requests.store'), [
            'absence_date' => $request->absence_date->toDateString(),
            'subject_id' => $request->subject_id,
            'reason_category_id' => $request->reason_category_id,
            'explanation' => 'This replacement is allowed after cancelling the first request.',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'declaration' => '1',
            'intent' => 'submit',
        ])->assertRedirect();

        $this->assertSame(2, ExcuseRequest::where('student_id', $request->student_id)
            ->where('subject_id', $request->subject_id)
            ->whereDate('absence_date', $request->absence_date)
            ->count());
    }

    public function test_student_can_edit_date_subject_and_attachment_on_a_returned_request(): void
    {
        Storage::fake('local');
        [$request, $studentUser] = $this->makeRequest();
        $request->update(['status' => 'returned']);
        $student = Student::findOrFail($request->student_id);
        $newSubject = Subject::create(['code' => 'EDIT102', 'name' => 'Updated Subject', 'course_id' => $student->course_id, 'year_level' => 1]);
        InstructorAssignment::create(['faculty_id' => $request->facilitator_id, 'course_id' => $student->course_id, 'subject_id' => $newSubject->id, 'year_level' => 1, 'is_active' => true]);
        $newDate = now()->subDay()->toDateString();

        $this->actingAs($studentUser)->get(route('requests.edit', $request))
            ->assertOk()->assertSee('Edit Excuse Request')->assertSee('Detailed explanation');

        $this->actingAs($studentUser)->patch(route('requests.update', $request), [
            'absence_date' => $newDate,
            'subject_id' => $newSubject->id,
            'reason_category_id' => $request->reason_category_id,
            'explanation' => 'The complete excuse request has been updated by the student.',
            'start_time' => '08:00',
            'end_time' => '09:30',
            'guardian_name' => 'Updated Guardian',
            'guardian_contact' => '09123456789',
            'document' => UploadedFile::fake()->create('updated-proof.pdf', 100, 'application/pdf'),
        ])->assertRedirect()->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('returned', $request->status);
        $this->assertSame($newSubject->id, $request->subject_id);
        $this->assertSame($newDate, $request->absence_date->toDateString());
        $this->assertSame('Updated Guardian', $request->guardian_name);
        $document = $request->documents()->sole();
        $this->assertSame('updated-proof.pdf', $document->original_name);
        Storage::disk('local')->assertExists($document->path);
        $this->assertDatabaseHas('request_status_histories', [
            'excuse_request_id' => $request->id,
            'new_status' => 'returned',
            'action_by' => $studentUser->id,
            'remarks' => 'Request details and supporting attachment updated by student.',
        ]);
    }

    private function makeRequest(): array
    {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create(['user_id' => $studentUser->id, 'student_number' => '2026-0200', 'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1]);
        $faculty = Faculty::create(['employee_number' => 'FAC-DEL', 'name' => 'Maria Reyes']);
        $subject = Subject::create(['code' => 'DEL101', 'name' => 'Deletion Test', 'course_id' => $course->id, 'year_level' => 1]);
        $year = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);
        InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'subject_id' => $subject->id, 'year_level' => 1, 'is_active' => true]);
        $request = ExcuseRequest::create(['student_id' => $student->id, 'subject_id' => $subject->id, 'facilitator_id' => $faculty->id, 'academic_year_id' => $year->id, 'semester_id' => $semester->id, 'absence_date' => now()->toDateString(), 'reason_category_id' => $reason->id, 'explanation' => 'Test request.', 'status' => 'rejected']);

        return [$request, $studentUser];
    }
}
