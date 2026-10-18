<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AccountArchive;
use App\Models\Course;
use App\Models\DailyAbsenceReport;
use App\Models\ExcuseRequest;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
use App\Models\Message;
use App\Models\ReasonCategory;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AccountArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_student_saves_request_attendance_and_private_files(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id, 'student_number' => '26-1001',
            'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1,
            'assessment_form_path' => 'assessments/form.pdf',
        ]);
        Storage::disk('local')->put('assessments/form.pdf', 'assessment contents');
        Storage::disk('local')->put('documents/evidence.pdf', 'document contents');
        $faculty = Faculty::create(['name' => 'Instructor']);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming', 'course_id' => $course->id]);
        $assignment = InstructorAssignment::create([
            'faculty_id' => $faculty->id, 'course_id' => $course->id,
            'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $section->id,
        ]);
        $report = DailyAbsenceReport::create([
            'student_id' => $student->id, 'instructor_assignment_id' => $assignment->id,
            'report_date' => today()->toDateString(), 'attendance_status' => 'absent',
            'adviser_comment' => 'Followed up with guardian.',
        ]);
        $request = ExcuseRequest::create([
            'student_id' => $student->id, 'subject_id' => $subject->id, 'facilitator_id' => $faculty->id,
            'academic_year_id' => AcademicYear::create(['name' => '2026-2027'])->id,
            'semester_id' => Semester::create(['name' => 'First'])->id,
            'absence_date' => today()->toDateString(),
            'reason_category_id' => ReasonCategory::create(['name' => 'Illness'])->id,
            'explanation' => 'Sick', 'status' => 'approved', 'reference_number' => 'ARCHIVED-ACCOUNT-SLIP',
            'approved_at' => now(), 'slip_remark' => 'EXCUSED',
        ]);
        $request->slipIssuance()->create(['snapshot' => [
            'student_name' => 'Name at issuance', 'student_number' => $student->student_number,
            'subject_codes' => ['PF101'], 'absence_date' => today()->toDateString(),
            'slip_remark' => 'EXCUSED', 'official_remarks' => 'Original decision.',
        ]]);
        $request->slipAmendments()->create([
            'action_by' => $admin->id, 'slip_remark' => 'CONDITIONAL',
            'remarks' => 'Revised after review.',
        ]);
        $request->histories()->create(['new_status' => 'draft', 'action_by' => $studentUser->id]);
        $request->documents()->create([
            'disk' => 'local', 'path' => 'documents/evidence.pdf', 'original_name' => 'evidence.pdf',
            'mime_type' => 'application/pdf', 'size' => 17,
        ]);

        $this->actingAs($admin)->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'));
        $archive = AccountArchive::where('original_user_id', $studentUser->id)->firstOrFail();
        $this->assertSame('deleted', $archive->outcome);
        $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
        $this->assertDatabaseMissing('excuse_requests', ['id' => $request->id]);
        $this->assertSame($request->id, $archive->records['excuse_requests'][0]['id']);
        $this->assertSame('Followed up with guardian.', $archive->records['daily_absence_reports'][0]['adviser_comment']);
        $this->assertSame($report->id, $archive->records['daily_absence_reports'][0]['id']);
        $this->assertCount(1, $archive->records['request_status_histories']);
        $this->assertCount(1, $archive->records['slip_issuances']);
        $this->assertCount(1, $archive->records['slip_amendments']);
        $this->assertArrayNotHasKey('password', $archive->records['user']);
        $this->assertCount(2, $archive->files);
        foreach ($archive->files as $file) Storage::disk('local')->assertExists($file['archive_path']);
        Storage::disk('local')->assertMissing('assessments/form.pdf');
        Storage::disk('local')->assertMissing('documents/evidence.pdf');
        $this->actingAs($admin)->get(route('admin.account-archives.show', $archive))->assertOk()->assertSee('evidence.pdf');
        $this->actingAs($admin)->get(route('admin.account-archives.export', $archive))
            ->assertOk()->assertJsonPath('records.excuse_requests.0.id', $request->id);
        $this->actingAs($admin)->get(route('admin.account-archives.file', [$archive, 1]))
            ->assertOk()->assertDownload('evidence.pdf');
        auth()->logout();
        $this->get(route('verify', 'ARCHIVED-ACCOUNT-SLIP'))->assertOk()
            ->assertSee('AMENDED OFFICIAL SLIP')->assertSee('Name at issuance')
            ->assertSee('CONDITIONAL')->assertSee('Revised after review.');
    }

    public function test_referenced_faculty_account_is_archived_and_disabled_without_erasing_live_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $faculty = Faculty::create(['user_id' => $teacher->id, 'name' => 'Instructor']);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id, 'student_number' => '26-2001',
            'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1,
        ]);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming', 'course_id' => $course->id]);
        $request = ExcuseRequest::create([
            'student_id' => $student->id, 'subject_id' => $subject->id, 'facilitator_id' => $faculty->id,
            'academic_year_id' => AcademicYear::create(['name' => '2026-2027'])->id,
            'semester_id' => Semester::create(['name' => 'First'])->id,
            'absence_date' => today()->toDateString(),
            'reason_category_id' => ReasonCategory::create(['name' => 'Illness'])->id,
            'explanation' => 'Sick', 'status' => 'submitted',
        ]);

        $this->actingAs($admin)->delete(route('admin.accounts.destroy', $teacher))
            ->assertRedirect(route('admin.accounts.index'));
        $archive = AccountArchive::where('original_user_id', $teacher->id)->firstOrFail();
        $this->assertSame('deactivated', $archive->outcome);
        $this->assertFalse($teacher->fresh()->is_active);
        $this->assertDatabaseHas('excuse_requests', ['id' => $request->id]);
        $this->assertSame($request->id, $archive->records['excuse_requests'][0]['id']);
    }

    public function test_instructor_assignment_and_attendance_history_are_saved_before_cascade(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $faculty = Faculty::create(['user_id' => $teacher->id, 'name' => 'Instructor']);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id, 'student_number' => '26-3001',
            'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1,
        ]);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming', 'course_id' => $course->id]);
        $assignment = InstructorAssignment::create([
            'faculty_id' => $faculty->id, 'course_id' => $course->id,
            'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $section->id,
        ]);
        $report = DailyAbsenceReport::create([
            'student_id' => $student->id, 'instructor_assignment_id' => $assignment->id,
            'report_date' => today()->toDateString(), 'attendance_status' => 'late',
            'adviser_action' => 'Guardian contacted',
        ]);

        $this->actingAs($admin)->delete(route('admin.accounts.destroy', $teacher))
            ->assertRedirect(route('admin.accounts.index'));
        $archive = AccountArchive::where('original_user_id', $teacher->id)->firstOrFail();
        $this->assertSame('deleted', $archive->outcome);
        $this->assertDatabaseMissing('instructor_assignments', ['id' => $assignment->id]);
        $this->assertDatabaseMissing('daily_absence_reports', ['id' => $report->id]);
        $this->assertSame($assignment->id, $archive->records['instructor_assignments'][0]['id']);
        $this->assertSame('Guardian contacted', $archive->records['daily_absence_reports'][0]['adviser_action']);
    }

    public function test_only_administrators_can_read_archives(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->get(route('admin.account-archives.index'))->assertForbidden();
    }

    public function test_export_only_includes_messages_visible_to_the_administrator(): void
    {
        $first = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $second = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create(['user_id' => $studentUser->id, 'student_number' => '26-8001',
            'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1]);
        Message::create(['student_id' => $student->id, 'sender_id' => $studentUser->id,
            'recipient_id' => $first->id, 'body' => 'Private for first admin']);
        Message::create(['student_id' => $student->id, 'sender_id' => $studentUser->id,
            'recipient_id' => $second->id, 'body' => 'Private for second admin']);

        $this->actingAs($first)->delete(route('admin.students.destroy', $student))->assertRedirect();
        $archive = AccountArchive::where('original_user_id', $studentUser->id)->firstOrFail();
        $this->actingAs($first)->get(route('admin.account-archives.export', $archive))
            ->assertOk()->assertSee('Private for first admin')->assertDontSee('Private for second admin');
        $this->actingAs($second)->get(route('admin.account-archives.export', $archive))
            ->assertOk()->assertSee('Private for second admin')->assertDontSee('Private for first admin');
    }

    public function test_batch_archive_rolls_back_every_account_when_one_item_fails(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        Student::create(['user_id' => $studentUser->id, 'student_number' => '26-9001',
            'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1,
            'assessment_form_path' => 'assessments/form.pdf']);
        Storage::disk('local')->put('assessments/form.pdf', 'original');

        try {
            app(AccountArchiveService::class)->archiveManyAndRemove(collect([$studentUser, $studentUser]), $admin);
            $this->fail('The second archive should fail because its account was removed inside the transaction.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertDatabaseHas('users', ['id' => $studentUser->id]);
            $this->assertDatabaseCount('account_archives', 0);
            Storage::disk('local')->assertExists('assessments/form.pdf');
            $this->assertSame([], Storage::disk('local')->allFiles('account-archives'));
        }
    }
}
