@extends('layouts.app')

@section('title', 'Messages')

@section('content')
<div class="flex h-[calc(100vh-9rem)] min-h-[34rem] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm {{ $isStudent ? 'mx-auto max-w-4xl' : '' }}">
    @unless($isStudent)
        <aside class="hidden w-80 shrink-0 border-r border-slate-200 md:flex md:flex-col">
            <div class="border-b border-slate-200 p-5"><div class="flex items-center justify-between gap-3"><div><h1 class="text-2xl font-bold text-slate-900">Messages</h1><div class="mt-1 flex gap-3 text-xs font-semibold uppercase tracking-wide"><a href="{{ route('messages.index') }}" class="{{ request()->routeIs('messages.archived') ? 'text-slate-400' : 'text-[#123A63]' }}">Recent</a><a href="{{ route('messages.archived') }}" class="{{ request()->routeIs('messages.archived') ? 'text-[#123A63]' : 'text-slate-400' }}">Archived</a></div></div><button type="button" onclick="document.getElementById('new-chat-dialog').showModal()" class="rounded-lg bg-[#123A63] px-3 py-2 text-sm font-semibold text-white">New chat</button></div><input id="conversation-search" class="mt-4" placeholder="Search conversations..."></div>
            <div id="conversation-list" class="flex-1 overflow-y-auto">
                @forelse($students as $student)
                    <form method="post" action="{{ route('messages.select', $student) }}" data-student-name="{{ strtolower($student->user->name.' '.$student->student_number) }}" class="border-b border-slate-100 {{ $selectedStudent?->is($student) ? 'bg-blue-50' : '' }}">@csrf<button class="flex w-full gap-3 p-4 text-left hover:bg-slate-50">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-[#123A63] font-bold text-white">{{ strtoupper(substr($student->user->name, 0, 1)) }}</span>
                        <span class="min-w-0 flex-1"><span class="flex items-start justify-between gap-2"><strong class="truncate text-sm text-slate-900">{{ $student->user->name }}</strong>@if($student->unread_messages_count)<span class="rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white">{{ $student->unread_messages_count }}</span>@endif</span><span class="mt-1 block truncate text-xs text-slate-500">{{ $student->student_number }} · {{ $student->course?->code }}</span></span>
                    </button></form>
                @empty
                    <p class="p-6 text-center text-sm text-slate-500">{{ request()->routeIs('messages.archived') ? 'No archived conversations.' : 'No conversations yet. Student messages will appear here.' }}</p>
                @endforelse
            </div>
        </aside>
    @endunless

    <section class="flex min-w-0 flex-1 flex-col">
        @if($selectedStudent)
            <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-[#123A63] font-bold text-white">{{ strtoupper(substr($selectedStudent->user->name, 0, 1)) }}</span>
                <div class="min-w-0 flex-1"><h1 class="truncate font-bold text-slate-900">{{ $isStudent ? 'Administrative Support' : $selectedStudent->user->name }}</h1><p class="truncate text-xs text-slate-500">{{ $isStudent ? 'Message the administrator or program head' : $selectedStudent->student_number.' · '.$selectedStudent->course?->code.' Year '.$selectedStudent->year_level }}</p></div>
                @if($isStudent)
                    <form method="post" action="{{ route('messages.conversation.destroy', $selectedStudent) }}" onsubmit="return confirm('Delete this entire conversation from your account? Administrators will keep their copy.')">@csrf @method('DELETE')<button class="shrink-0 rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700">Delete</button></form>
                @else
                    <div class="flex shrink-0 gap-2"><form method="post" action="{{ route('messages.archive', $selectedStudent) }}">@csrf @method('PATCH')<button class="rounded-lg border px-3 py-2 text-sm font-semibold text-slate-700">{{ $selectedStudent->conversation_state?->archived_at ? 'Restore' : 'Archive' }}</button></form><form method="post" action="{{ route('messages.conversation.destroy', $selectedStudent) }}" onsubmit="return confirm('Delete this entire conversation from your account? The student will keep their copy.')">@csrf @method('DELETE')<button class="rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700">Delete</button></form></div>
                @endif
            </header>

            <div id="message-thread" class="flex-1 space-y-3 overflow-y-auto bg-slate-50 p-5" aria-live="polite">
                @forelse($messages as $message)
                    <div data-message-id="{{ $message->id }}" class="flex {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[78%] rounded-2xl px-4 py-3 {{ $message->unsent_at ? 'border border-slate-200 bg-slate-100 text-slate-500' : ($message->sender_id === auth()->id() ? 'rounded-br-md bg-[#123A63] text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-800') }}">
                            <p class="whitespace-pre-wrap break-words text-sm {{ $message->unsent_at ? 'italic' : '' }}">{{ $message->unsent_at ? 'This message was unsent.' : $message->body }}</p><p class="mt-1 text-[11px] {{ $message->unsent_at ? 'text-slate-400' : ($message->sender_id === auth()->id() ? 'text-blue-100' : 'text-slate-400') }}">{{ $isStudent && in_array($message->sender->role, ['admin', 'program_head']) ? $message->sender->maskedAdministrativeName() : $message->sender->name }} · {{ $message->created_at->format('M j, g:i A') }}{{ $message->edited_at && ! $message->unsent_at ? ' · Edited' : '' }}</p>
                            @if($message->sender_id === auth()->id() && ! $message->unsent_at)<div class="mt-2 flex justify-end gap-3 text-xs"><button type="button" data-edit-message="{{ $message->id }}" class="underline opacity-80 hover:opacity-100">Edit</button><button type="button" data-unsend-message="{{ $message->id }}" class="underline opacity-80 hover:opacity-100">Unsend</button></div>@endif
                        </div>
                    </div>
                @empty
                    <div id="empty-thread" class="grid h-full place-items-center text-center"><div><div class="mx-auto grid size-16 place-items-center rounded-full bg-blue-50 text-2xl text-[#123A63]">#</div><p class="mt-4 font-semibold text-slate-700">No messages yet</p><p class="mt-1 text-sm text-slate-500">Send a message to begin the conversation.</p></div></div>
                @endforelse
            </div>

            <form id="message-form" class="flex items-end gap-3 border-t border-slate-200 bg-white p-4">
                <textarea id="message-body" rows="1" maxlength="4000" required placeholder="Type a message..." class="max-h-32 min-h-11 resize-none"></textarea>
                <button id="message-send" class="h-11 shrink-0 rounded-xl bg-[#123A63] px-5 font-semibold text-white hover:bg-[#245B8E]">Send</button>
            </form>
        @else
            <div class="grid h-full place-items-center p-8 text-center"><div><p class="text-xl font-bold text-slate-800">No conversation selected</p><p class="mt-2 text-slate-500">Select a student to start messaging.</p></div></div>
        @endif
    </section>
</div>

@unless($isStudent)
<dialog id="new-chat-dialog" class="m-auto w-[min(32rem,calc(100%-2rem))] rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
    <div class="p-6">
        <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-bold text-slate-900">New chat</h2><p class="mt-1 text-sm text-slate-500">Search for a student to begin a conversation.</p></div><button type="button" onclick="document.getElementById('new-chat-dialog').close()" class="text-2xl leading-none text-slate-400" aria-label="Close">&times;</button></div>
        <input id="new-chat-search" class="mt-5" placeholder="Search name, student ID, or email..." autofocus>
        <div id="new-chat-students" class="mt-4 hidden max-h-96 overflow-y-auto rounded-xl border border-slate-200">
            @forelse($allStudents as $student)
                <form method="post" action="{{ route('messages.select', $student) }}" data-student-search="{{ strtolower($student->user->name.' '.$student->student_number.' '.$student->user->email) }}" class="border-b border-slate-100 last:border-b-0">@csrf<button class="flex w-full items-center gap-3 p-3 text-left hover:bg-blue-50">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-[#123A63] font-bold text-white">{{ strtoupper(substr($student->user->name, 0, 1)) }}</span>
                    <span class="min-w-0"><strong class="block truncate text-sm text-slate-900">{{ $student->user->name }}</strong><span class="block truncate text-xs text-slate-500">{{ $student->student_number }} · {{ $student->user->email }}</span></span>
                </button></form>
            @empty
                <p class="p-5 text-center text-sm text-slate-500">No student accounts found.</p>
            @endforelse
        </div>
        <p id="new-chat-empty" class="mt-4 hidden text-center text-sm text-slate-500">No matching students found.</p>
    </div>
</dialog>
@endunless

@if($selectedStudent)
<script>
(() => {
    const thread = document.getElementById('message-thread');
    const form = document.getElementById('message-form');
    const body = document.getElementById('message-body');
    const send = document.getElementById('message-send');
    const updateUrl = @json(route('messages.update', ['message' => '__MESSAGE__']));
    const unsendUrl = @json(route('messages.destroy', ['message' => '__MESSAGE__']));
    let lastId = Number(thread.querySelector('[data-message-id]:last-of-type')?.dataset.messageId || 0);

    function renderMessage(message) {
        document.getElementById('empty-thread')?.remove();
        let row = thread.querySelector(`[data-message-id="${message.id}"]`);
        if (!row) { row = document.createElement('div'); row.dataset.messageId = message.id; thread.append(row); }
        row.replaceChildren();
        row.className = `flex ${message.mine ? 'justify-end' : 'justify-start'}`;
        const bubble = document.createElement('div');
        bubble.className = `max-w-[78%] rounded-2xl px-4 py-3 ${message.unsent ? 'border border-slate-200 bg-slate-100 text-slate-500' : (message.mine ? 'rounded-br-md bg-[#123A63] text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-800')}`;
        const text = document.createElement('p');
        text.className = `whitespace-pre-wrap break-words text-sm ${message.unsent ? 'italic' : ''}`;
        text.textContent = message.body;
        const meta = document.createElement('p');
        meta.className = `mt-1 text-[11px] ${message.unsent ? 'text-slate-400' : (message.mine ? 'text-blue-100' : 'text-slate-400')}`;
        meta.textContent = `${message.sender} · ${message.time}${message.edited && !message.unsent ? ' · Edited' : ''}`;
        bubble.append(text, meta);
        if (message.mine && !message.unsent) {
            const actions = document.createElement('div'); actions.className = 'mt-2 flex justify-end gap-3 text-xs';
            const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'underline opacity-80 hover:opacity-100'; edit.textContent = 'Edit'; edit.dataset.editMessage = message.id;
            const unsend = document.createElement('button'); unsend.type = 'button'; unsend.className = 'underline opacity-80 hover:opacity-100'; unsend.textContent = 'Unsend'; unsend.dataset.unsendMessage = message.id;
            actions.append(edit, unsend); bubble.append(actions);
        }
        row.append(bubble);
        lastId = Math.max(lastId, Number(message.id));
        return row;
    }

    async function poll() {
        if (document.hidden) return;
        try {
            const response = await fetch(@json(route('messages.updates', $selectedStudent)) + `?after=${lastId}`, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (response.ok) (await response.json()).messages.forEach(renderMessage);
        } catch (error) {}
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!body.value.trim()) return;
        send.disabled = true;
        try {
            const response = await fetch(@json(route('messages.store', $selectedStudent)), {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}, body: JSON.stringify({body: body.value})});
            if (response.ok) { renderMessage((await response.json()).message); body.value = ''; body.style.height = ''; thread.scrollTop = thread.scrollHeight; }
        } finally { send.disabled = false; body.focus(); }
    });
    body.addEventListener('input', () => { body.style.height = ''; body.style.height = `${Math.min(body.scrollHeight, 128)}px`; });
    body.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); } });
    thread.addEventListener('click', async event => {
        const editButton = event.target.closest('[data-edit-message]');
        const unsendButton = event.target.closest('[data-unsend-message]');
        const id = editButton?.dataset.editMessage || unsendButton?.dataset.unsendMessage;
        if (!id) return;
        if (editButton) {
            const current = editButton.closest('[data-message-id]').querySelector('p').textContent;
            const replacement = prompt('Edit message:', current);
            if (replacement === null || !replacement.trim() || replacement === current) return;
            const response = await fetch(updateUrl.replace('__MESSAGE__', id), {method: 'PUT', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}, body: JSON.stringify({body: replacement})});
            if (response.ok) renderMessage((await response.json()).message);
        } else if (confirm('Unsend this message for everyone?')) {
            const response = await fetch(unsendUrl.replace('__MESSAGE__', id), {method: 'DELETE', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}});
            if (response.ok) renderMessage((await response.json()).message);
        }
    });
    thread.scrollTop = thread.scrollHeight;
    setInterval(poll, 3000);
})();
</script>
@endif
@unless($isStudent)
<script>
document.getElementById('conversation-search')?.addEventListener('input', event => {
    document.querySelectorAll('#conversation-list [data-student-name]').forEach(item => item.classList.toggle('hidden', !item.dataset.studentName.includes(event.target.value.toLowerCase().trim())));
});
document.getElementById('new-chat-search')?.addEventListener('input', event => {
    const query = event.target.value.toLowerCase().trim();
    const results = document.getElementById('new-chat-students');
    const empty = document.getElementById('new-chat-empty');
    const students = [...document.querySelectorAll('#new-chat-students [data-student-search]')];
    if (!query) {
        results.classList.add('hidden');
        empty.classList.add('hidden');
        students.forEach(item => item.classList.add('hidden'));
        return;
    }
    let matches = 0;
    students.forEach(item => { const visible = item.dataset.studentSearch.includes(query); item.classList.toggle('hidden', !visible); if (visible) matches++; });
    results.classList.toggle('hidden', matches === 0);
    empty.classList.toggle('hidden', matches > 0);
});
document.getElementById('new-chat-dialog')?.addEventListener('close', () => {
    const search = document.getElementById('new-chat-search');
    search.value = '';
    search.dispatchEvent(new Event('input'));
});
</script>
@endunless
@endsection
