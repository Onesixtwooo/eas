<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_and_admin_can_exchange_private_messages(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $studentWithoutMessages = $this->createStudent('quiet@example.com', '2026-1009');
        $admin = User::factory()->create(['name' => 'Odiether Catabona', 'role' => 'admin', 'is_active' => true]);

        $studentResponse = $this->actingAs($student->user)
            ->postJson(route('messages.store', $student), ['body' => 'Hello administrator.'])
            ->assertCreated();
        $studentMessageId = $studentResponse->json('message.id');

        $this->actingAs($admin)->post(route('messages.select', $student))->assertRedirect(route('messages.index'));
        $this->actingAs($admin)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Hello administrator.')
            ->assertSee($student->user->name)
            ->assertSee('New chat')
            ->assertSee($studentWithoutMessages->student_number)
            ->assertViewHas('students', fn ($students) => ! $students->contains('id', $studentWithoutMessages->id));

        $this->actingAs($admin)->post(route('messages.select', $studentWithoutMessages))->assertRedirect(route('messages.index'));
        $this->actingAs($admin)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee($studentWithoutMessages->user->name)
            ->assertSee('No messages yet');

        $adminResponse = $this->actingAs($admin)
            ->postJson(route('messages.store', $student), ['body' => 'How can I help?'])
            ->assertCreated();
        $adminMessageId = $adminResponse->json('message.id');

        $studentUpdates = $this->actingAs($student->user)
            ->getJson(route('messages.updates', ['student' => $student, 'after' => $studentMessageId]))
            ->assertOk()
            ->assertJsonFragment(['body' => 'How can I help?', 'sender' => 'O**** C*****']);
        $this->assertStringNotContainsString('Odiether Catabona', $studentUpdates->getContent());

        $this->actingAs($student->user)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('O**** C*****')
            ->assertDontSee('Odiether Catabona');

        $this->actingAs($admin)
            ->putJson(route('messages.update', $adminMessageId), ['body' => 'How may I help?'])
            ->assertOk()
            ->assertJsonPath('message.edited', true);

        $this->actingAs($student->user)
            ->putJson(route('messages.update', $adminMessageId), ['body' => 'Unauthorized edit'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson(route('messages.destroy', $adminMessageId))
            ->assertOk()
            ->assertJsonPath('message.unsent', true)
            ->assertJsonPath('message.body', 'This message was unsent.');

        $this->assertDatabaseHas('messages', [
            'id' => $adminMessageId,
            'body' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('messages.archive', $student))
            ->assertSessionHas('success');
        $this->actingAs($admin)
            ->get(route('messages.index'))
            ->assertViewHas('students', fn ($students) => ! $students->contains('id', $student->id));
        $this->actingAs($admin)
            ->get(route('messages.archived'))
            ->assertViewHas('students', fn ($students) => $students->contains('id', $student->id));

        $this->actingAs($student->user)
            ->postJson(route('messages.store', $student), ['body' => 'I have a new question.'])
            ->assertCreated();
        $this->actingAs($admin)
            ->get(route('messages.index'))
            ->assertViewHas('students', fn ($students) => $students->contains('id', $student->id));
        $this->actingAs($admin)
            ->get(route('messages.archived'))
            ->assertViewHas('students', fn ($students) => ! $students->contains('id', $student->id));

        $this->actingAs($admin)
            ->delete(route('messages.conversation.destroy', $student))
            ->assertSessionHas('success');
        $this->actingAs($admin)->post(route('messages.select', $student))->assertRedirect(route('messages.index'));
        $this->actingAs($admin)
            ->get(route('messages.index'))
            ->assertSee('No messages yet');
        $this->actingAs($student->user)
            ->get(route('messages.index'))
            ->assertSee('Hello administrator.');
    }

    public function test_student_cannot_open_another_students_conversation(): void
    {
        $student = $this->createStudent('one@example.com', '2026-1001');
        $other = $this->createStudent('two@example.com', '2026-1002');

        $this->actingAs($student->user)
            ->getJson(route('messages.updates', $other))
            ->assertForbidden();
    }

    public function test_student_can_delete_their_conversation_without_deleting_the_admin_copy(): void
    {
        $student = $this->createStudent('student@example.com', '2026-1001');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($student->user)
            ->postJson(route('messages.store', $student), ['body' => 'Student message'])
            ->assertCreated();
        $this->actingAs($admin)
            ->postJson(route('messages.store', $student), ['body' => 'Administrator reply'])
            ->assertCreated();

        $this->actingAs($student->user)
            ->delete(route('messages.conversation.destroy', $student))
            ->assertRedirect(route('messages.index'))
            ->assertSessionHas('success');

        $this->actingAs($student->user)
            ->get(route('messages.index'))
            ->assertSee('No messages yet')
            ->assertDontSee('Student message')
            ->assertDontSee('Administrator reply');

        $this->actingAs($admin)->post(route('messages.select', $student));
        $this->actingAs($admin)
            ->get(route('messages.index'))
            ->assertSee('Student message')
            ->assertSee('Administrator reply');

        $this->actingAs($admin)
            ->postJson(route('messages.store', $student), ['body' => 'New administrator message'])
            ->assertCreated();
        $this->actingAs($student->user)
            ->get(route('messages.index'))
            ->assertSee('New administrator message')
            ->assertDontSee('Administrator reply');
    }

    public function test_faculty_cannot_access_administrative_messages(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'is_active' => true]);

        $this->actingAs($faculty)->get(route('messages.index'))->assertForbidden();
    }

    private function createStudent(string $email, string $number): Student
    {
        $course = Course::firstOrCreate(['code' => 'BSIT'], ['name' => 'BS Information Technology']);
        $section = Section::firstOrCreate(['course_id' => $course->id, 'year_level' => 1, 'name' => 'A']);
        $user = User::factory()->create(['email' => $email, 'role' => 'student', 'is_active' => true]);

        return Student::create([
            'user_id' => $user->id,
            'student_number' => $number,
            'student_type' => 'regular',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'year_level' => 1,
        ]);
    }
}
