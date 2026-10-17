<?php

namespace App\Http\Controllers;

use App\Models\ExcuseRequest;
use App\Models\Message;
use App\Models\MessageConversationState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    private const MESSAGE_PAGE_SIZE = 50;

    public function index(Request $request)
    {
        $user = $request->user();
        $this->authorizeMessenger($user);
        $isStudent = $user->role === 'student';
        $student = $isStudent ? $user->student : null;
        abort_if($isStudent && ! $student, 403);

        $archived = $request->routeIs('messages.archived');
        $threadPages = null;
        if ($isStudent) {
            $availableRecipients = $this->availableRecipients($student);
            $recipients = $availableRecipients->merge(
                User::whereIn('id', $student->messages()->whereNotNull('recipient_id')->distinct()->pluck('recipient_id'))->get()
            )->unique('id')->values();
            $threads = $recipients->map(fn ($recipient) => $this->threadSummary($user, $student, $recipient->id, $recipient))
                ->filter(fn ($thread) => $thread['available'] || $thread['last_id'] > 0);
            if ($student->messages()->whereNull('recipient_id')->exists()) {
                $threads->push($this->threadSummary($user, $student, 0, null));
            }
            $allStudents = collect();
        } else {
            $studentSearch = trim((string) $request->query('student_search', ''));
            $allStudents = ($user->role === 'faculty' ? $this->eligibleInstructorStudents($user) : Student::query())
                ->with(['user', 'course'])
                ->when($studentSearch !== '', fn ($query) => $query->where(fn ($matches) => $matches
                    ->where('student_number', 'like', '%'.addcslashes($studentSearch, '%_\\').'%')
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.addcslashes($studentSearch, '%_\\').'%'))))
                ->orderBy('id')->paginate(20, ['*'], 'student_page')->withQueryString();

            $threadQuery = Message::query()
                ->leftJoin('message_conversation_states as viewer_state', function ($join) use ($user) {
                    $join->on('viewer_state.student_id', '=', 'messages.student_id')
                        ->on('viewer_state.partner_id', '=', DB::raw('COALESCE(messages.recipient_id, 0)'))
                        ->where('viewer_state.user_id', '=', $user->id);
                })
                ->where(fn ($query) => $query->where('messages.recipient_id', $user->id)
                    ->when(in_array($user->role, ['admin', 'program_head'], true), fn ($query) => $query->orWhereNull('messages.recipient_id')))
                ->whereRaw('messages.id > COALESCE(viewer_state.deleted_before_message_id, 0)')
                ->when($archived, fn ($query) => $query->whereNotNull('viewer_state.archived_at'),
                    fn ($query) => $query->whereNull('viewer_state.archived_at'))
                ->when($user->role === 'faculty', function ($query) use ($user) {
                    if (! $user->faculty) return $query->whereRaw('1 = 0');
                    return $query->whereHas('student.requests', fn ($requests) => $requests
                        ->whereIn('status', ['approved', 'acknowledged', 'completed'])
                        ->where(fn ($assigned) => $assigned->where('facilitator_id', $user->faculty->id)
                            ->orWhereHas('facilitators', fn ($faculty) => $faculty->whereKey($user->faculty->id))));
                })
                ->selectRaw('messages.student_id, COALESCE(messages.recipient_id, 0) as partner_id, MAX(messages.id) as last_id, SUM(CASE WHEN messages.sender_id != ? AND messages.id > COALESCE(viewer_state.last_read_message_id, 0) THEN 1 ELSE 0 END) as unread', [$user->id])
                ->groupBy('messages.student_id', 'messages.recipient_id')
                ->orderByDesc('last_id');
            $threadPages = $threadQuery->paginate(20, ['*'], 'thread_page')->withQueryString();
            $threadStudents = Student::with(['user', 'course'])->whereIn('id', $threadPages->pluck('student_id'))->get()->keyBy('id');
            $threads = $threadPages->getCollection()->map(function ($row) use ($threadStudents, $user, $archived) {
                $candidate = $threadStudents->get($row->student_id);
                if (! $candidate) return null;
                return [
                    'student' => $candidate, 'partner_id' => (int) $row->partner_id,
                    'recipient' => $row->partner_id ? $user : null, 'last_id' => (int) $row->last_id,
                    'unread' => (int) $row->unread, 'archived' => $archived,
                    'available' => false,
                ];
            })->filter()->values();
            $recipients = collect();
            $availableRecipients = collect();
        }

        $threads = $threads->filter(fn ($thread) => $thread['last_id'] > 0 && $thread['archived'] === $archived)
            ->sortByDesc('last_id')->values();

        $selectedStudent = null;
        $selectedPartner = null;
        if ($isStudent) {
            $partnerId = $request->query('partner');
            if ($partnerId !== null) {
                $selectedStudent = $student;
                $selectedPartner = (int) $partnerId;
            } elseif ($threads->isNotEmpty()) {
                $selectedStudent = $student;
                $selectedPartner = $threads->first()['partner_id'];
            } elseif ($recipients->isNotEmpty()) {
                $selectedStudent = $student;
                $selectedPartner = $recipients->first()->id;
            }
        } else {
            $studentId = $request->query('student');
            $selectedStudent = $studentId ? Student::with(['user', 'course'])->findOrFail((int) $studentId) : ($threads->first()['student'] ?? null);
            $selectedPartner = $selectedStudent ? (int) ($request->query('partner')
                ?? ($studentId ? $user->id : $threads->first()['partner_id'])) : null;
        }

        $messages = collect();
        $selectedRecipient = null;
        $canSend = false;
        $state = null;
        $hasEarlierMessages = false;
        $messageSyncAt = now()->toIso8601String();
        if ($selectedStudent && $selectedPartner !== null) {
            $this->authorizeThread($user, $selectedStudent, $selectedPartner);
            $selectedRecipient = $selectedPartner ? User::find($selectedPartner) : null;
            $canSend = $selectedPartner > 0 && $this->canMessage($user, $selectedStudent, $selectedPartner);
            $state = $this->state($user, $selectedStudent, $selectedPartner);
            $messages = $this->threadMessages($selectedStudent, $selectedPartner, $state->deleted_before_message_id)
                ->latest('id')->limit(self::MESSAGE_PAGE_SIZE)->get()->reverse()->values();
            $hasEarlierMessages = $messages->isNotEmpty() && $this->threadMessages($selectedStudent, $selectedPartner, $state->deleted_before_message_id)
                ->where('id', '<', $messages->first()->id)->exists();
            $this->markRead($state, $messages->last()?->id);
        }

        return view('messages.index', compact('isStudent', 'threads', 'threadPages', 'allStudents', 'selectedStudent',
            'selectedPartner', 'selectedRecipient', 'messages', 'canSend', 'state', 'recipients', 'availableRecipients',
            'hasEarlierMessages', 'messageSyncAt'));
    }

    public function select(Request $request, Student $student)
    {
        $this->authorizeMessenger($request->user());
        abort_if($request->user()->role === 'student', 403);
        abort_unless($this->canMessage($request->user(), $student, $request->user()->id)
            || $this->threadMessages($student, $request->user()->id)->exists(), 403);

        return redirect()->route('messages.index', ['student' => $student->id]);
    }

    public function selectRecipient(Request $request, User $partner)
    {
        abort_unless($request->user()->role === 'student' && $request->user()->student, 403);
        $this->authorizeThread($request->user(), $request->user()->student, $partner->id);

        return redirect()->route('messages.index', ['partner' => $partner->id]);
    }

    public function store(Request $request, Student $student, int $partner): JsonResponse
    {
        $this->authorizeMessenger($request->user());
        abort_unless($this->canMessage($request->user(), $student, $partner), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message = $student->messages()->create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $partner,
            'body' => trim($data['body']),
        ])->load('sender');
        MessageConversationState::where('student_id', $student->id)->where('partner_id', $partner)
            ->update(['archived_at' => null]);

        return response()->json(['message' => $this->messageData($message, $request->user())], 201);
    }

    public function updates(Request $request, Student $student, int $partner): JsonResponse
    {
        $data = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
            'since' => ['nullable', 'date'],
            'changed_after' => ['nullable', 'integer', 'min:0'],
        ]);
        $user = $request->user();
        $this->authorizeThread($user, $student, $partner);
        $state = $this->state($user, $student, $partner);
        $after = max((int) ($data['after'] ?? 0), (int) $state->deleted_before_message_id);
        $syncAt = now()->toIso8601String();
        $changed = collect();
        $hasMoreChanges = false;
        $changedAfter = (int) ($data['changed_after'] ?? 0);
        if (isset($data['since'])) {
            $changed = $this->threadMessages($student, $partner, $state->deleted_before_message_id)
                ->where('id', '<=', $after)
                ->where('id', '>', $changedAfter)
                ->where('updated_at', '>=', \Illuminate\Support\Carbon::parse($data['since'])->subSecond())
                ->where('updated_at', '<=', $syncAt)
                ->orderBy('id')->limit(self::MESSAGE_PAGE_SIZE + 1)->get();
            $hasMoreChanges = $changed->count() > self::MESSAGE_PAGE_SIZE;
            $changed = $changed->take(self::MESSAGE_PAGE_SIZE);
            $changedAfter = (int) ($changed->last()?->id ?? $changedAfter);
        }
        $remaining = self::MESSAGE_PAGE_SIZE - $changed->count();
        $newQuery = $this->threadMessages($student, $partner, $state->deleted_before_message_id)
            ->where('id', '>', $after);
        $newMessages = $remaining > 0 ? $newQuery->orderBy('id')->limit($remaining + 1)->get() : collect();
        $hasMoreNew = $remaining > 0 ? $newMessages->count() > $remaining : $newQuery->exists();
        $newMessages = $newMessages->take($remaining);
        $this->markRead($state, $newMessages->last()?->id);
        $messages = $changed->concat($newMessages);

        return response()->json([
            'messages' => $messages->map(fn ($message) => $this->messageData($message, $user)),
            'sync_at' => $syncAt,
            'changed_after' => $changedAfter,
            'has_more_changes' => $hasMoreChanges,
            'has_more_new' => $hasMoreNew,
        ]);
    }

    public function history(Request $request, Student $student, int $partner): JsonResponse
    {
        $data = $request->validate(['before' => ['required', 'integer', 'min:1']]);
        $user = $request->user();
        $this->authorizeThread($user, $student, $partner);
        $state = $this->state($user, $student, $partner);
        $messages = $this->threadMessages($student, $partner, $state->deleted_before_message_id)
            ->where('id', '<', $data['before'])->latest('id')->limit(self::MESSAGE_PAGE_SIZE)->get()->reverse()->values();
        $hasMore = $messages->isNotEmpty() && $this->threadMessages($student, $partner, $state->deleted_before_message_id)
            ->where('id', '<', $messages->first()->id)->exists();

        return response()->json(['messages' => $messages->map(fn ($message) => $this->messageData($message, $user)), 'has_more' => $hasMore]);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $this->authorizeOwnedMessage($request, $message);
        abort_if($message->unsent_at, 422, 'An unsent message cannot be edited.');
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message->update(['body' => trim($data['body']), 'edited_at' => now()]);

        return response()->json(['message' => $this->messageData($message->load('sender'), $request->user())]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $this->authorizeOwnedMessage($request, $message);
        $message->update(['body' => null, 'unsent_at' => now()]);

        return response()->json(['message' => $this->messageData($message->load('sender'), $request->user())]);
    }

    public function archive(Request $request, Student $student, int $partner)
    {
        $this->authorizeThread($request->user(), $student, $partner);
        $state = $this->state($request->user(), $student, $partner);
        $state->update(['archived_at' => $state->archived_at ? null : now()]);

        return redirect()->route($state->archived_at ? 'messages.archived' : 'messages.index')
            ->with('success', $state->archived_at ? 'Conversation archived.' : 'Conversation restored.');
    }

    public function deleteConversation(Request $request, Student $student, int $partner)
    {
        $this->authorizeThread($request->user(), $student, $partner);
        $state = $this->state($request->user(), $student, $partner);
        $state->update([
            'deleted_before_message_id' => $this->threadMessages($student, $partner)->max('id') ?? 0,
            'archived_at' => null,
        ]);

        return redirect()->route('messages.index')->with('success', 'Conversation deleted from your account only.');
    }

    private function authorizeMessenger(User $user): void
    {
        abort_unless($user->is_active && in_array($user->role, ['student', 'admin', 'program_head', 'faculty'], true), 403);
    }

    private function authorizeThread(User $user, Student $student, int $partner): void
    {
        $this->authorizeMessenger($user);
        abort_if($user->role === 'student' && $user->student?->id !== $student->id, 403);
        if ($partner === 0) {
            abort_unless($user->role === 'student' || in_array($user->role, ['admin', 'program_head'], true), 403);
            abort_unless($this->threadMessages($student, 0)->exists(), 404);
            return;
        }
        abort_unless($user->role === 'student' || $partner === $user->id, 403);
        abort_unless($this->canMessage($user, $student, $partner)
            || $this->threadMessages($student, $partner)->exists(), 403);
    }

    private function canMessage(User $user, Student $student, int $partner): bool
    {
        if ($partner <= 0 || ($user->role === 'student' && $user->student?->id !== $student->id)) return false;
        if ($user->role !== 'student' && $partner !== $user->id) return false;
        $staff = User::find($partner);
        if (! $staff?->is_active) return false;

        return in_array($staff->role, ['admin', 'program_head'], true)
            || ($staff->role === 'faculty' && $this->canMessageInstructor($staff, $student));
    }

    private function canMessageInstructor(User $user, Student $student): bool
    {
        return $user->role === 'faculty' && $user->is_active && $user->faculty
            && $this->eligibleInstructorStudents($user)->whereKey($student->id)->exists();
    }

    private function eligibleInstructorStudents(User $user)
    {
        $students = Student::query();
        if ($user->role !== 'faculty' || ! $user->is_active || ! $user->faculty) {
            return $students->whereRaw('1 = 0');
        }

        $facultyId = $user->faculty->id;

        return $students->whereExists(fn ($assignments) => $assignments->selectRaw('1')
            ->from('instructor_assignments as message_assignment')
            ->where('message_assignment.faculty_id', $facultyId)
            ->whereColumn('message_assignment.course_id', 'students.course_id')
            ->where('message_assignment.is_active', true)->whereNull('message_assignment.retired_at')
            ->where(fn ($sections) => $sections->whereColumn('message_assignment.section_id', 'students.section_id')
                ->orWhereNull('message_assignment.section_id'))
            ->where(fn ($enrollment) => $enrollment
                ->where(fn ($regular) => $regular->where('students.student_type', '!=', 'irregular')
                    ->whereColumn('message_assignment.year_level', 'students.year_level'))
                ->orWhere(fn ($irregular) => $irregular->where('students.student_type', 'irregular')
                    ->whereExists(fn ($subjects) => $subjects->selectRaw('1')->from('student_subject')
                        ->whereColumn('student_subject.student_id', 'students.id')
                        ->whereColumn('student_subject.subject_id', 'message_assignment.subject_id'))))
            ->where(fn ($block) => $block->whereNotNull('message_assignment.section_id')
                ->orWhereNotExists(fn ($specific) => $specific->selectRaw('1')
                    ->from('instructor_assignments as specific_assignment')
                    ->whereColumn('specific_assignment.course_id', 'students.course_id')
                    ->whereColumn('specific_assignment.subject_id', 'message_assignment.subject_id')
                    ->whereColumn('specific_assignment.section_id', 'students.section_id')
                    ->where('specific_assignment.is_active', true)->whereNull('specific_assignment.retired_at')
                    ->where(fn ($year) => $year->where('students.student_type', 'irregular')
                        ->orWhereColumn('specific_assignment.year_level', 'students.year_level'))))
            ->whereExists(fn ($requests) => $requests->selectRaw('1')->from('excuse_requests as message_request')
                ->whereColumn('message_request.student_id', 'students.id')
                ->whereIn('message_request.status', ['approved', 'acknowledged', 'completed'])
                ->where(fn ($covered) => $covered
                    ->where(fn ($primary) => $primary->where('message_request.facilitator_id', $facultyId)
                        ->whereColumn('message_request.subject_id', 'message_assignment.subject_id'))
                    ->orWhereExists(fn ($subjects) => $subjects->selectRaw('1')
                        ->from('excuse_request_subject as request_subject')
                        ->whereColumn('request_subject.excuse_request_id', 'message_request.id')
                        ->whereColumn('request_subject.subject_id', 'message_assignment.subject_id')
                        ->where('request_subject.facilitator_id', $facultyId)))));
    }

    private function confirmedRequests(Student $student)
    {
        return ExcuseRequest::where('student_id', $student->id)
            ->whereIn('status', ['approved', 'acknowledged', 'completed']);
    }

    private function availableRecipients(Student $student)
    {
        $instructors = $this->confirmedRequests($student)
            ->with(['facilitator.user', 'facilitators.user'])
            ->get()
            ->flatMap(fn ($request) => $request->facilitators->pluck('user')->push($request->facilitator?->user))
            ->filter(fn ($user) => $user?->exists && $this->canMessageInstructor($user, $student))
            ->unique('id')->values();
        return User::whereIn('role', ['admin', 'program_head'])->where('is_active', true)->orderBy('name')->get()
            ->merge($instructors)->unique('id')->values();
    }

    private function threadSummary(User $user, Student $student, int $partner, ?User $recipient): array
    {
        $state = MessageConversationState::where(['user_id' => $user->id, 'student_id' => $student->id, 'partner_id' => $partner])->first();
        $query = $this->threadMessages($student, $partner);
        $cutoff = $state?->deleted_before_message_id ?? 0;
        $lastId = (clone $query)->where('id', '>', $cutoff)->max('id') ?? 0;
        $unread = (clone $query)->where('id', '>', max($cutoff, $state?->last_read_message_id ?? 0))
            ->where('sender_id', '!=', $user->id)->count();
        return [
            'student' => $student, 'partner_id' => $partner, 'recipient' => $recipient,
            'last_id' => $lastId, 'unread' => $unread, 'archived' => (bool) $state?->archived_at,
            'available' => $partner > 0 && $this->canMessage($user, $student, $partner),
        ];
    }

    private function threadMessages(Student $student, int $partner, ?int $cutoff = null)
    {
        $query = $student->messages()->with('sender')->where('recipient_id', $partner ?: null);
        if ($cutoff) $query->where('id', '>', $cutoff);
        return $query;
    }

    private function state(User $user, Student $student, int $partner): MessageConversationState
    {
        return MessageConversationState::firstOrCreate([
            'user_id' => $user->id, 'student_id' => $student->id, 'partner_id' => $partner,
        ]);
    }

    private function markRead(MessageConversationState $state, ?int $messageId): void
    {
        if ($messageId && $messageId > $state->last_read_message_id) {
            $state->update(['last_read_message_id' => $messageId]);
        }
    }

    private function authorizeOwnedMessage(Request $request, Message $message): void
    {
        $this->authorizeMessenger($request->user());
        abort_unless($message->sender_id === $request->user()->id, 403);
    }

    private function messageData(Message $message, User $viewer): array
    {
        return [
            'id' => $message->id,
            'body' => $message->unsent_at ? 'This message was unsent.' : $message->body,
            'mine' => $message->sender_id === $viewer->id,
            'sender' => $message->sender->name,
            'time' => $message->created_at->format('M j, g:i A'),
            'edited' => (bool) $message->edited_at,
            'unsent' => (bool) $message->unsent_at,
        ];
    }
}
