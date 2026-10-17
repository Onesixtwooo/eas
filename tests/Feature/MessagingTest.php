<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\AcademicYear;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_choose_one_admin_and_messages_stay_private(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $first = User::factory()->create(['name' => 'First Admin', 'role' => 'admin', 'is_active' => true]);
        $second = User::factory()->create(['name' => 'Second Admin', 'role' => 'admin', 'is_active' => true]);

        $this->actingAs($student->user)->get(route('messages.index'))
            ->assertOk()->assertSee('First Admin')->assertSee('Second Admin');
        $this->actingAs($student->user)->post(route('messages.recipient.select', $first))
            ->assertRedirect(route('messages.index', ['partner' => $first->id]));

        $message = $this->actingAs($student->user)
            ->postJson(route('messages.store', [$student, $first->id]), ['body' => 'Private question'])
            ->assertCreated()->json('message.id');
        $this->assertDatabaseHas('messages', ['id' => $message, 'recipient_id' => $first->id]);

        $this->actingAs($first)->get(route('messages.index', ['student' => $student->id]))
            ->assertOk()->assertSee('Private question');
        $this->actingAs($second)->get(route('messages.index'))
            ->assertOk()->assertDontSee('Private question');
        $this->actingAs($second)->getJson(route('messages.updates', [$student, $first->id]))
            ->assertForbidden();
        $this->actingAs($second)->postJson(route('messages.store', [$student, $first->id]), ['body' => 'Intrusion'])
            ->assertForbidden();

        $this->actingAs($first)->postJson(route('messages.store', [$student, $first->id]), ['body' => 'My reply'])
            ->assertCreated();
        $this->actingAs($student->user)->getJson(route('messages.updates', [
            'student' => $student, 'partner' => $first->id, 'after' => $message,
        ]))->assertOk()->assertJsonFragment(['body' => 'My reply', 'sender' => 'First Admin']);
    }

    public function test_instructor_only_sees_students_with_confirmed_slips_addressed_to_them(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $other = $this->createStudent('other@example.com', '2026-1002');
        $teacher = User::factory()->create(['name' => 'Assigned Instructor', 'role' => 'faculty', 'is_active' => true]);
        $faculty = Faculty::create(['user_id' => $teacher->id, 'name' => $teacher->name]);
        $subject = Subject::create(['code' => 'PF101', 'name' => 'Programming', 'course_id' => $student->course_id]);
        $assignment = InstructorAssignment::create([
            'faculty_id' => $faculty->id, 'course_id' => $student->course_id,
            'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $student->section_id,
        ]);

        $this->actingAs($teacher)->get(route('messages.index'))
            ->assertOk()->assertDontSee($student->user->name)->assertDontSee($other->user->name);
        $this->actingAs($student->user)->get(route('messages.index'))
            ->assertOk()->assertDontSee('Assigned Instructor');
        $this->actingAs($student->user)->postJson(route('messages.store', [$student, $teacher->id]), ['body' => 'Early question'])
            ->assertForbidden();

        ExcuseRequest::create([
            'student_id' => $student->id, 'subject_id' => $subject->id, 'facilitator_id' => $faculty->id,
            'academic_year_id' => AcademicYear::create(['name' => '2026-2027'])->id,
            'semester_id' => Semester::create(['name' => 'First'])->id,
            'absence_date' => today()->toDateString(),
            'reason_category_id' => ReasonCategory::create(['name' => 'Illness'])->id,
            'explanation' => 'Illness', 'status' => 'approved',
        ]);

        $this->actingAs($student->user)->get(route('messages.index'))
            ->assertOk()->assertSee('Assigned Instructor');
        $this->actingAs($other->user)->get(route('messages.index'))
            ->assertOk()->assertDontSee('Assigned Instructor');
        $this->actingAs($other->user)->postJson(route('messages.store', [$other, $teacher->id]), ['body' => 'Wrong block'])
            ->assertForbidden();
        $this->actingAs($teacher)->get(route('messages.index'))
            ->assertOk()->assertSee($student->user->name)->assertDontSee($other->user->name);
        $this->actingAs($student->user)->postJson(route('messages.store', [$student, $teacher->id]), ['body' => 'Class question'])
            ->assertCreated();
        $this->actingAs($teacher)->get(route('messages.index', ['student' => $student->id]))
            ->assertOk()->assertSee('Class question');
        $this->actingAs($teacher)->postJson(route('messages.store', [$other, $teacher->id]), ['body' => 'Wrong student'])
            ->assertForbidden();

        $newSubject = Subject::create(['code' => 'PF102', 'name' => 'Data Structures', 'course_id' => $student->course_id]);
        $assignment->update(['subject_id' => $newSubject->id]);
        $this->actingAs($student->user)->postJson(route('messages.store', [$student, $teacher->id]), ['body' => 'After subject reassignment'])
            ->assertForbidden();
        $this->actingAs($teacher)->postJson(route('messages.store', [$student, $teacher->id]), ['body' => 'After subject reassignment'])
            ->assertForbidden();
        $this->actingAs($teacher)->get(route('messages.index', ['student' => $student->id]))
            ->assertOk()->assertSee('Class question')->assertSee('available for reading only');
        $this->actingAs($student->user)->get(route('messages.index'))
            ->assertOk()->assertDontSee('action="'.route('messages.recipient.select', $teacher).'"', false);

        $assignment->update(['subject_id' => $subject->id]);
        $otherSection = Section::create(['course_id' => $student->course_id, 'year_level' => 1, 'name' => 'B']);
        $student->update(['section_id' => $otherSection->id]);
        $this->actingAs($student->user)->postJson(route('messages.store', [$student, $teacher->id]), ['body' => 'After block change'])
            ->assertForbidden();
    }

    public function test_instructor_recipient_search_filters_before_paginating(): void
    {
        $first = $this->createStudent('first@example.com', '2026-9000');
        $teacher = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $faculty = Faculty::create(['user_id' => $teacher->id, 'name' => $teacher->name]);
        $subject = Subject::create(['code' => 'PF900', 'name' => 'Programming', 'course_id' => $first->course_id]);
        InstructorAssignment::create(['faculty_id' => $faculty->id, 'course_id' => $first->course_id,
            'subject_id' => $subject->id, 'year_level' => 1, 'section_id' => $first->section_id]);
        $year = AcademicYear::create(['name' => '2026-2027']);
        $semester = Semester::create(['name' => 'First']);
        $reason = ReasonCategory::create(['name' => 'Illness']);

        for ($i = 0; $i < 20; $i++) {
            $student = $i === 0 ? $first : $this->createStudent("other{$i}@example.com", '2026-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT));
            $student->update(['year_level' => 2]);
            ExcuseRequest::create(['student_id' => $student->id, 'subject_id' => $subject->id,
                'facilitator_id' => $faculty->id, 'academic_year_id' => $year->id,
                'semester_id' => $semester->id, 'absence_date' => today(),
                'reason_category_id' => $reason->id, 'explanation' => 'Illness', 'status' => 'approved']);
        }
        // Put the eligible student after all ineligible candidates in ID order.
        $eligible = $this->createStudent('last@example.com', '2026-9999');
        ExcuseRequest::create(['student_id' => $eligible->id, 'subject_id' => $subject->id,
            'facilitator_id' => $faculty->id, 'academic_year_id' => $year->id,
            'semester_id' => $semester->id, 'absence_date' => today(),
            'reason_category_id' => $reason->id, 'explanation' => 'Illness', 'status' => 'approved']);

        $this->actingAs($teacher)->get(route('messages.index'))
            ->assertOk()
            ->assertViewHas('allStudents', fn ($students) => $students->total() === 1
                && $students->first()->id === $eligible->id);
    }

    public function test_read_state_is_per_recipient_and_per_conversation(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $first = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $second = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($student->user)->postJson(route('messages.store', [$student, $first->id]), ['body' => 'For first'])
            ->assertCreated();
        $this->actingAs($student->user)->postJson(route('messages.store', [$student, $second->id]), ['body' => 'For second'])
            ->assertCreated();

        $this->actingAs($first)->get(route('messages.index', ['student' => $student->id]))->assertOk();
        $this->actingAs($second)->get(route('messages.index'))
            ->assertViewHas('threads', fn ($threads) => $threads->first()['unread'] === 1);
        $this->assertDatabaseHas('message_conversation_states', [
            'user_id' => $first->id, 'student_id' => $student->id, 'partner_id' => $first->id,
        ]);
    }

    public function test_opening_legacy_history_does_not_clear_another_admins_unread_state(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $first = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $second = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Message::create(['student_id' => $student->id, 'sender_id' => $student->user_id, 'body' => 'Old question']);

        $this->actingAs($first)->get(route('messages.index', ['student' => $student->id, 'partner' => 0]))->assertOk();
        $this->actingAs($second)->get(route('messages.index'))
            ->assertViewHas('threads', fn ($threads) => $threads->first()['partner_id'] === 0
                && $threads->first()['unread'] === 1);
    }

    public function test_student_cannot_open_another_students_conversation(): void
    {
        $student = $this->createStudent('one@example.com', '2026-1001');
        $other = $this->createStudent('two@example.com', '2026-1002');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($student->user)->getJson(route('messages.updates', [$other, $admin->id]))
            ->assertForbidden();
    }

    public function test_legacy_shared_messages_remain_readable_without_joining_new_threads(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Message::create(['student_id' => $student->id, 'sender_id' => $student->user_id, 'body' => 'Old support question']);

        $this->actingAs($student->user)->get(route('messages.index', ['partner' => 0]))
            ->assertOk()->assertSee('Old support question')->assertSee('read only');
        $this->actingAs($admin)->get(route('messages.index', ['student' => $student->id, 'partner' => 0]))
            ->assertOk()->assertSee('Old support question');
        $this->actingAs($student->user)->postJson(route('messages.store', [$student, 0]), ['body' => 'New question'])
            ->assertForbidden();
    }

    public function test_conversation_delete_is_local_and_new_messages_reappear(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($student->user)->postJson(route('messages.store', [$student, $admin->id]), ['body' => 'First'])
            ->assertCreated();
        $this->actingAs($student->user)->delete(route('messages.conversation.destroy', [$student, $admin->id]))
            ->assertRedirect(route('messages.index'));
        $this->actingAs($student->user)->get(route('messages.index', ['partner' => $admin->id]))
            ->assertOk()->assertDontSee('First');
        $this->actingAs($admin)->get(route('messages.index', ['student' => $student->id]))
            ->assertOk()->assertSee('First');
        $this->actingAs($admin)->postJson(route('messages.store', [$student, $admin->id]), ['body' => 'Second'])
            ->assertCreated();
        $this->actingAs($student->user)->get(route('messages.index', ['partner' => $admin->id]))
            ->assertOk()->assertSee('Second')->assertDontSee('First');
    }

    public function test_only_sender_can_edit_or_unsend_and_archive_is_personal(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $messageId = $this->actingAs($student->user)
            ->postJson(route('messages.store', [$student, $admin->id]), ['body' => 'Original'])
            ->assertCreated()->json('message.id');

        $this->actingAs($admin)->putJson(route('messages.update', $messageId), ['body' => 'Changed'])
            ->assertForbidden();
        $this->actingAs($student->user)->putJson(route('messages.update', $messageId), ['body' => 'Changed'])
            ->assertOk()->assertJsonPath('message.edited', true);
        $this->actingAs($student->user)->deleteJson(route('messages.destroy', $messageId))
            ->assertOk()->assertJsonPath('message.unsent', true);

        $this->actingAs($admin)->patch(route('messages.archive', [$student, $admin->id]))
            ->assertRedirect(route('messages.archived'));
        $this->actingAs($admin)->get(route('messages.archived'))
            ->assertViewHas('threads', fn ($threads) => $threads->count() === 1);
        $this->actingAs($student->user)->get(route('messages.index'))
            ->assertViewHas('threads', fn ($threads) => $threads->count() === 1);
    }

    public function test_chat_messages_are_throttled(): void
    {
        $student = $this->createStudent('flooder@example.com', '2026-1099');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($student->user)->postJson(route('messages.store', [$student, $admin->id]), ['body' => "Message {$i}"])
                ->assertCreated();
        }
        $this->actingAs($student->user)->postJson(route('messages.store', [$student, $admin->id]), ['body' => 'Extra'])
            ->assertStatus(429);
    }

    public function test_older_messages_can_be_loaded_without_crossing_conversations(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $first = null;
        for ($i = 1; $i <= 205; $i++) {
            $message = Message::create(['student_id' => $student->id, 'sender_id' => $student->user_id,
                'recipient_id' => $admin->id, 'body' => "Message {$i}"]);
            $first ??= $message;
        }

        $this->actingAs($student->user)->get(route('messages.index', ['partner' => $admin->id]))
            ->assertOk()->assertSee('Load earlier messages')->assertDontSee('Message 1<', false)
            ->assertViewHas('messages', fn ($messages) => $messages->count() === 50);
        $this->actingAs($student->user)->getJson(route('messages.history', [$student, $admin->id, 'before' => $first->id + 155]))
            ->assertOk()->assertJsonCount(50, 'messages')->assertJsonPath('has_more', true);
        $this->actingAs($student->user)->getJson(route('messages.history', [$student, $admin->id, 'before' => $first->id + 6]))
            ->assertOk()->assertJsonPath('messages.0.id', $first->id)->assertJsonPath('has_more', false);
        $this->actingAs($other)->getJson(route('messages.history', [$student, $admin->id, 'before' => $first->id + 6]))
            ->assertForbidden();
    }

    public function test_polling_reports_edits_and_unsends_to_existing_messages(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $message = Message::create(['student_id' => $student->id, 'sender_id' => $student->user_id,
            'recipient_id' => $admin->id, 'body' => 'Original']);
        $since = now()->subMinute()->toIso8601String();

        $this->actingAs($student->user)->putJson(route('messages.update', $message), ['body' => 'Edited'])
            ->assertOk();
        $this->actingAs($admin)->getJson(route('messages.updates', [$student, $admin->id, 'after' => $message->id, 'since' => $since]))
            ->assertOk()->assertJsonPath('messages.0.id', $message->id)
            ->assertJsonPath('messages.0.body', 'Edited')->assertJsonPath('messages.0.edited', true);
        $this->actingAs($student->user)->deleteJson(route('messages.destroy', $message))->assertOk();
        $this->actingAs($admin)->getJson(route('messages.updates', [$student, $admin->id, 'after' => $message->id, 'since' => $since]))
            ->assertOk()->assertJsonPath('messages.0.unsent', true)
            ->assertJsonPath('messages.0.body', 'This message was unsent.');
    }

    public function test_staff_inbox_and_new_chat_search_are_paged(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $first = null;
        for ($i = 1; $i <= 22; $i++) {
            $student = $this->createStudent("paged{$i}@example.com", sprintf('2026-%04d', $i));
            $first ??= $student;
            Message::create(['student_id' => $student->id, 'sender_id' => $student->user_id,
                'recipient_id' => $admin->id, 'body' => "Conversation {$i}"]);
        }

        $this->actingAs($admin)->get(route('messages.index'))
            ->assertOk()->assertViewHas('threads', fn ($threads) => $threads->count() === 20);
        $this->actingAs($admin)->get(route('messages.index', ['thread_page' => 2]))
            ->assertOk()->assertViewHas('threads', fn ($threads) => $threads->count() === 2);
        $this->actingAs($admin)->get(route('messages.index', ['student_search' => $first->student_number]))
            ->assertOk()->assertViewHas('allStudents', fn ($students) => $students->count() === 1
                && $students->first()->id === $first->id);
    }

    public function test_edited_message_polling_uses_a_bounded_cursor(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $since = now()->subMinute()->toIso8601String();
        $lastId = 0;
        for ($i = 1; $i <= 205; $i++) {
            $message = Message::create(['student_id' => $student->id, 'sender_id' => $student->user_id,
                'recipient_id' => $admin->id, 'body' => "Original {$i}", 'edited_at' => now()]);
            $lastId = $message->id;
        }

        $ids = [];
        $cursor = 0;
        do {
            $response = $this->actingAs($admin)->getJson(route('messages.updates', [
                $student, $admin->id, 'after' => $lastId, 'since' => $since,
                'changed_after' => $cursor,
            ]))->assertOk();
            $this->assertLessThanOrEqual(50, count($response->json('messages')));
            $ids = array_merge($ids, array_column($response->json('messages'), 'id'));
            $cursor = $response->json('changed_after');
        } while ($response->json('has_more_changes'));
        $this->assertEqualsCanonicalizing(
            range($lastId - 204, $lastId),
            $ids
        );
    }

    public function test_new_message_polling_loads_fifty_at_a_time(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        for ($i = 1; $i <= 105; $i++) {
            Message::create(['student_id' => $student->id, 'sender_id' => $student->user_id,
                'recipient_id' => $admin->id, 'body' => "New {$i}"]);
        }

        $after = 0;
        $counts = [];
        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($admin)->getJson(route('messages.updates', [
                $student, $admin->id, 'after' => $after,
            ]))->assertOk();
            $response->assertJsonPath('has_more_new', $i < 2);
            $counts[] = count($response->json('messages'));
            $after = $response->json('messages')[count($response->json('messages')) - 1]['id'];
        }
        $this->assertSame([50, 50, 5], $counts);
    }

    private function createStudent(string $email, string $number): Student
    {
        $course = Course::firstOrCreate(['code' => 'BSIT'], ['name' => 'BS Information Technology']);
        $section = Section::firstOrCreate(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $user = User::factory()->create(['email' => $email, 'role' => 'student', 'is_active' => true]);

        return Student::create([
            'user_id' => $user->id, 'student_number' => $number, 'student_type' => 'regular',
            'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1,
        ]);
    }
}
