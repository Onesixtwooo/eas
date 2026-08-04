<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageConversationState;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['student', 'admin', 'program_head'], true), 403);
        $isStudent = $user->role === 'student';
        $students = collect();
        $allStudents = collect();

        if ($isStudent) {
            $selectedStudent = $user->student;
        } else {
            $allStudents = Student::with(['user', 'course', 'section'])->orderBy('id')->get();
            $states = MessageConversationState::where('user_id', $user->id)->get()->keyBy('student_id');
            $students = Student::with(['user', 'course', 'section'])
                ->whereHas('messages')
                ->withMax('messages', 'id')
                ->withMax('messages', 'created_at')
                ->withCount(['messages as unread_messages_count' => fn ($query) => $query
                    ->whereNull('read_at')->whereHas('sender', fn ($sender) => $sender->where('role', 'student'))])
                ->orderByDesc('messages_max_created_at')
                ->orderBy('id')
                ->get()
                ->filter(function ($student) use ($states, $request) {
                    $state = $states->get($student->id);
                    $student->conversation_state = $state;
                    $hasVisibleMessages = $student->messages_max_id > ($state?->deleted_before_message_id ?? 0);
                    $isArchived = (bool) $state?->archived_at;

                    return $hasVisibleMessages && ($request->routeIs('messages.archived') ? $isArchived : ! $isArchived);
                })->values();
            $selectedStudent = $allStudents->firstWhere('id', (int) $request->session()->get('message_selected_student_id')) ?? $students->first();
            if ($selectedStudent) $selectedStudent->conversation_state = $states->get($selectedStudent->id);
        }

        $messages = $selectedStudent
            ? $this->visibleMessages($request, $selectedStudent)
            : collect();

        if ($selectedStudent) {
            $this->markReceivedAsRead($selectedStudent, $user->id);
        }

        return view('messages.index', compact('students', 'allStudents', 'selectedStudent', 'messages', 'isStudent'));
    }

    public function store(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudent($request, $student);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message = $student->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => trim($data['body']),
        ])->load('sender');
        if (in_array($request->user()->role, ['admin', 'program_head'], true)) {
            MessageConversationState::where(['user_id' => $request->user()->id, 'student_id' => $student->id])->update(['archived_at' => null]);
        } else {
            MessageConversationState::where('student_id', $student->id)->update(['archived_at' => null]);
        }

        return response()->json(['message' => $this->messageData($message)], 201);
    }

    public function select(Request $request, Student $student)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'program_head'], true), 403);
        $request->session()->put('message_selected_student_id', $student->id);

        return redirect()->route('messages.index');
    }

    public function updates(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudent($request, $student);
        $messages = $this->visibleMessages($request, $student);
        $this->markReceivedAsRead($student, $request->user()->id);

        return response()->json(['messages' => $messages->map(fn ($message) => $this->messageData($message))]);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $this->authorizeOwnedMessage($request, $message);
        abort_if($message->unsent_at, 422, 'An unsent message cannot be edited.');
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message->update(['body' => trim($data['body']), 'edited_at' => now()]);

        return response()->json(['message' => $this->messageData($message->load('sender'))]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $this->authorizeOwnedMessage($request, $message);
        $message->update(['body' => null, 'unsent_at' => now()]);

        return response()->json(['message' => $this->messageData($message->load('sender'))]);
    }

    public function archive(Request $request, Student $student)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'program_head'], true), 403);
        $state = MessageConversationState::firstOrNew(['user_id' => $request->user()->id, 'student_id' => $student->id]);
        $state->archived_at = $state->archived_at ? null : now();
        $state->save();
        $request->session()->forget('message_selected_student_id');

        return redirect()->route($state->archived_at ? 'messages.index' : 'messages.archived')
            ->with('success', $state->archived_at ? 'Conversation archived.' : 'Conversation restored.');
    }

    public function deleteConversation(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);
        MessageConversationState::updateOrCreate(
            ['user_id' => $request->user()->id, 'student_id' => $student->id],
            ['deleted_before_message_id' => $student->messages()->max('id'), 'archived_at' => null]
        );
        $request->session()->forget('message_selected_student_id');

        return redirect()->route('messages.index')->with('success', 'Conversation deleted from your account only.');
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'program_head'], true)
                || ($request->user()->role === 'student' && $request->user()->student?->is($student)),
            403
        );
    }

    private function markReceivedAsRead(Student $student, int $userId): void
    {
        $student->messages()->where('sender_id', '!=', $userId)->whereNull('read_at')->update(['read_at' => now()]);
    }

    private function authorizeOwnedMessage(Request $request, Message $message): void
    {
        abort_unless($message->sender_id === $request->user()->id, 403);
    }

    private function visibleMessages(Request $request, Student $student)
    {
        $query = $student->messages()->with('sender');
        $cutoff = MessageConversationState::where([
            'user_id' => $request->user()->id,
            'student_id' => $student->id,
        ])->value('deleted_before_message_id');
        if ($cutoff) $query->where('id', '>', $cutoff);

        return $query->oldest()->limit(200)->get();
    }

    private function messageData(Message $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->unsent_at ? 'This message was unsent.' : $message->body,
            'mine' => $message->sender_id === auth()->id(),
            'sender' => auth()->user()->role === 'student' && in_array($message->sender->role, ['admin', 'program_head'], true)
                ? $message->sender->maskedAdministrativeName()
                : $message->sender->name,
            'time' => $message->created_at->format('M j, g:i A'),
            'edited' => (bool) $message->edited_at,
            'unsent' => (bool) $message->unsent_at,
        ];
    }
}
