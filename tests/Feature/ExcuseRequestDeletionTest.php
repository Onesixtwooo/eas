<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\ExcuseRequest;
use App\Models\ExcuseRequestArchive;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
use App\Models\ReasonCategory;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SupportingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExcuseRequestDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-15 07:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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
        $archive = ExcuseRequestArchive::where('original_request_id', $request->id)->firstOrFail();
        $this->assertSame($request->id, $archive->records['request']['id']);
        $this->assertCount(1, $archive->records['supporting_documents']);
        Storage::disk('local')->assertExists($archive->files[0]['archive_path']);
        $this->actingAs($admin)->get(route('admin.request-archives.index'))->assertOk()->assertSee('Request Archives');
        $this->actingAs($admin)->get(route('admin.request-archives.export', $archive))
            ->assertOk()->assertJsonPath('records.request.id', $request->id);
        $this->actingAs($admin)->get(route('admin.request-archives.file', [$archive, 0]))
            ->assertOk()->assertDownload('proof.pdf');
    }

    public function test_archived_approved_request_remains_publicly_verifiable_without_exposing_attachments(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        [$request] = $this->makeRequest();
        $request->update(['status' => 'approved', 'reference_number' => 'ARCHIVED-SLIP-123']);
        Storage::disk('local')->put('supporting-documents/private.pdf', 'private evidence');
        $request->documents()->create(['disk' => 'local', 'path' => 'supporting-documents/private.pdf',
            'original_name' => 'private.pdf', 'mime_type' => 'application/pdf', 'size' => 16]);

        $this->actingAs($admin)->delete(route('requests.destroy', $request))->assertRedirect();
        auth()->logout();
        $this->get(route('verify', 'ARCHIVED-SLIP-123'))->assertOk()
            ->assertSee('VALID OFFICIAL SLIP')->assertSee('ARCHIVED-SLIP-123')
            ->assertDontSee('private.pdf')->assertDontSee('private evidence');
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

        $this->actingAs($studentUser)->from('https://outside.example/phishing')->get('/a-page-that-does-not-exist')
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

    public function test_invalid_document_does_not_leave_a_draft_and_student_can_retry(): void
    {
        Storage::fake('local');
        [$existing, $studentUser] = $this->makeRequest();
        $existing->update(['status' => 'cancelled']);
        $input = [
            'absence_date' => now()->toDateString(),
            'subject_id' => $existing->subject_id,
            'reason_category_id' => $existing->reason_category_id,
            'explanation' => 'This request should be retried with a valid document.',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'declaration' => '1',
            'intent' => 'submit',
        ];
        foreach (['proof.txt' => 'text/plain', 'proof.pdf' => 'application/pdf'] as $name => $mimeType) {
            $this->actingAs($studentUser)->post(route('requests.store'), $input + [
                'document' => UploadedFile::fake()->create($name, 100, $mimeType),
            ])->assertSessionHasErrors('document');
        }

        $this->assertSame(1, ExcuseRequest::where('student_id', $existing->student_id)->count());
        $this->assertDatabaseCount('supporting_documents', 0);

        $this->post(route('requests.store'), $input)
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(2, ExcuseRequest::where('student_id', $existing->student_id)->count());
    }

    public function test_supporting_image_is_reencoded_before_it_is_stored(): void
    {
        Storage::fake('local');
        [$existing, $studentUser] = $this->makeRequest();
        $existing->update(['status' => 'cancelled']);
        $image = UploadedFile::fake()->image('proof.png', 200, 200);
        file_put_contents($image->getRealPath(), '<?php echo "unsafe"; ?>', FILE_APPEND);

        $this->actingAs($studentUser)->post(route('requests.store'), [
            'absence_date' => now()->toDateString(),
            'subject_id' => $existing->subject_id,
            'reason_category_id' => $existing->reason_category_id,
            'explanation' => 'This supporting image should be rewritten before it is stored.',
            'declaration' => '1',
            'intent' => 'submit',
            'document' => $image,
        ])->assertRedirect()->assertSessionHas('success');

        $document = SupportingDocument::sole();
        $this->assertSame('image/png', $document->mime_type);
        $this->assertSame('proof.png', $document->original_name);
        $this->assertSame(Storage::disk('local')->size($document->path), $document->size);
        $this->assertStringNotContainsString('<?php', Storage::disk('local')->get($document->path));
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

    public function test_student_can_cancel_a_draft_or_returned_request(): void
    {
        [$request, $studentUser] = $this->makeRequest();
        $request->update(['status' => 'draft']);

        $this->actingAs($studentUser)->post(route('requests.cancel', $request))
            ->assertRedirect(route('requests.index'))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $request->fresh()->status);

        $request->update(['status' => 'returned']);

        $this->actingAs($studentUser)->post(route('requests.cancel', $request))
            ->assertRedirect(route('requests.index'))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $request->fresh()->status);
    }

    public function test_student_can_edit_details_but_not_attachment_on_a_returned_request(): void
    {
        Storage::fake('local');
        [$request, $studentUser] = $this->makeRequest();
        $request->update(['status' => 'returned']);
        Storage::disk('local')->put('supporting-documents/original.pdf', 'original');
        $original = $request->documents()->create(['disk' => 'local', 'path' => 'supporting-documents/original.pdf', 'original_name' => 'original.pdf', 'mime_type' => 'application/pdf', 'size' => 8]);
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
        ])->assertRedirect()->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('returned', $request->status);
        $this->assertSame($newSubject->id, $request->subject_id);
        $this->assertSame($newDate, $request->absence_date->toDateString());
        $this->assertSame('Updated Guardian', $request->guardian_name);
        $document = $request->documents()->sole();
        $this->assertSame($original->id, $document->id);
        Storage::disk('local')->assertExists($document->path);
        $this->assertDatabaseHas('request_status_histories', [
            'excuse_request_id' => $request->id,
            'new_status' => 'returned',
            'action_by' => $studentUser->id,
            'remarks' => 'Request details updated by student.',
        ]);

        $this->actingAs($studentUser)->patch(route('requests.update', $request), [
            'absence_date' => $newDate,
            'subject_id' => $newSubject->id,
            'reason_category_id' => $request->reason_category_id,
            'explanation' => 'The complete excuse request has been updated by the student.',
            'start_time' => '08:00',
            'end_time' => '09:30',
            'document' => UploadedFile::fake()->image('replacement.png', 200, 200),
        ])->assertSessionHasErrors('document');
        $this->assertSame($original->id, $request->documents()->sole()->id);
    }

    public function test_student_can_replace_an_attachment_only_while_request_is_a_draft(): void
    {
        Storage::fake('local');
        [$request, $studentUser] = $this->makeRequest();
        $request->update(['status' => 'draft']);
        Storage::disk('local')->put('supporting-documents/original.pdf', 'original');
        $original = $request->documents()->create(['disk' => 'local', 'path' => 'supporting-documents/original.pdf', 'original_name' => 'original.pdf', 'mime_type' => 'application/pdf', 'size' => 8]);

        $this->actingAs($studentUser)->get(route('requests.show', $request))
            ->assertOk()->assertSee('Replace supporting document');
        $this->patch(route('requests.attachment.update', $request), [
            'document' => UploadedFile::fake()->create('new-proof.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('document');
        $this->assertSame($original->id, $request->documents()->sole()->id);
        $this->patch(route('requests.attachment.update', $request), [
            'document' => UploadedFile::fake()->image('draft-proof.png', 200, 200),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('draft-proof.png', $request->documents()->sole()->original_name);
        $this->assertSame('image/png', $request->documents()->sole()->mime_type);
        $this->assertDatabaseMissing('supporting_documents', ['id' => $original->id]);
        Storage::disk('local')->assertMissing($original->path);

        foreach (['submitted', 'returned', 'approved'] as $status) {
            $request->update(['status' => $status]);
            $this->actingAs($studentUser)->get(route('requests.show', $request))
                ->assertDontSee('Replace supporting document');
            $this->patch(route('requests.attachment.update', $request), [
                'document' => UploadedFile::fake()->image('blocked.png', 200, 200),
            ])->assertForbidden();
            $this->assertSame('draft-proof.png', $request->documents()->sole()->original_name);
        }
    }

    public function test_student_cannot_change_details_after_admin_starts_review(): void
    {
        [$request, $studentUser] = $this->makeRequest();
        $request->update(['status' => 'under_review']);
        $originalExplanation = $request->explanation;

        $this->actingAs($studentUser)->patch(route('requests.update', $request), [
            'absence_date' => $request->absence_date->toDateString(),
            'subject_id' => $request->subject_id,
            'reason_category_id' => $request->reason_category_id,
            'explanation' => 'This edit must be rejected after administrative review begins.',
            'start_time' => '08:00',
            'end_time' => '09:00',
        ])->assertForbidden();

        $this->assertSame($originalExplanation, $request->fresh()->explanation);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    public function test_attachment_change_is_rejected_after_review_starts(): void
    {
        Storage::fake('local');
        [$request, $studentUser] = $this->makeRequest();
        $request->update(['status' => 'draft']);
        Storage::disk('local')->put('supporting-documents/original.pdf', 'original');
        $original = $request->documents()->create([
            'disk' => 'local', 'path' => 'supporting-documents/original.pdf',
            'original_name' => 'original.pdf', 'mime_type' => 'application/pdf', 'size' => 8,
        ]);
        $request->update(['status' => 'under_review']);

        $this->actingAs($studentUser)->patch(route('requests.attachment.update', $request), [
            'document' => UploadedFile::fake()->image('new.png', 200, 200),
        ])->assertForbidden();

        $this->assertSame($original->id, $request->documents()->sole()->id);
        Storage::disk('local')->assertExists($original->path);
        $this->assertSame([$original->path], Storage::disk('local')->allFiles('supporting-documents'));
    }

    public function test_student_can_submit_one_request_for_multiple_subjects(): void
    {
        [$existing, $studentUser] = $this->makeRequest();
        $existing->update(['status' => 'cancelled']);
        $student = Student::findOrFail($existing->student_id);
        $secondSubject = Subject::create(['code' => 'MULTI102', 'name' => 'Second Covered Class', 'course_id' => $student->course_id, 'year_level' => 1]);
        InstructorAssignment::create(['faculty_id' => $existing->facilitator_id, 'course_id' => $student->course_id, 'subject_id' => $secondSubject->id, 'year_level' => 1, 'is_active' => true]);

        $this->actingAs($studentUser)->post(route('requests.store'), [
            'absence_date' => now()->toDateString(),
            'subject_ids' => [$existing->subject_id, $secondSubject->id],
            'reason_category_id' => $existing->reason_category_id,
            'explanation' => 'One absence request should cover both of these scheduled subjects.',
            'start_time' => '08:00',
            'end_time' => '11:00',
            'declaration' => '1',
            'intent' => 'submit',
        ])->assertRedirect()->assertSessionHas('success');

        $request = ExcuseRequest::where('status', 'submitted')->sole();
        $this->assertSame('07:30', substr($request->start_time, 0, 5));
        $this->assertSame('07:30', substr($request->end_time, 0, 5));
        $this->assertEqualsCanonicalizing(
            [$existing->subject_id, $secondSubject->id],
            $request->subjects()->pluck('subjects.id')->all(),
        );
        $this->actingAs($studentUser)->get(route('requests.show', $request))
            ->assertOk()->assertSee('DEL101')->assertSee('MULTI102');
        $this->actingAs($studentUser)->get(route('requests.index'))
            ->assertOk()
            ->assertSee('2 classes')
            ->assertSee('covered-classes-'.$request->id)
            ->assertSee('Second Covered Class');
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
