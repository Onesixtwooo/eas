@extends('layouts.app')

@section('title', 'Messages')

@section('content')
<div class="flex h-[calc(100vh-9rem)] min-h-[34rem] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <aside class="flex w-64 shrink-0 flex-col border-r border-slate-200 md:w-80">
        <div class="border-b border-slate-200 p-4">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-xl font-bold text-slate-900">Messages</h1>
                <button type="button" onclick="document.getElementById('new-chat-dialog').showModal()" class="rounded-lg bg-[#123A63] px-3 py-2 text-xs font-semibold text-white">New chat</button>
            </div>
            <div class="mt-3 flex gap-3 text-xs font-semibold uppercase">
                <a href="{{ route('messages.index') }}" class="{{ request()->routeIs('messages.archived') ? 'text-slate-400' : 'text-[#123A63]' }}">Recent</a>
                <a href="{{ route('messages.archived') }}" class="{{ request()->routeIs('messages.archived') ? 'text-[#123A63]' : 'text-slate-400' }}">Archived</a>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto">
            @forelse($threads as $thread)
                @php($person = $isStudent ? $thread['recipient'] : $thread['student']->user)
                @php($label = $thread['partner_id'] === 0 ? 'Previous administrative support' : ($person?->name ?? 'Former staff account'))
                <a href="{{ route(request()->routeIs('messages.archived') ? 'messages.archived' : 'messages.index', $isStudent ? ['partner' => $thread['partner_id']] : ['student' => $thread['student']->id, 'partner' => $thread['partner_id']]) }}"
                   class="flex gap-3 border-b border-slate-100 p-4 hover:bg-slate-50 {{ $selectedStudent?->id === $thread['student']->id && $selectedPartner === $thread['partner_id'] ? 'bg-blue-50' : '' }}">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-[#123A63] font-bold text-white">{{ strtoupper(substr($label, 0, 1)) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-2"><strong class="truncate text-sm text-slate-900">{{ $label }}</strong>
                            @if($thread['unread'])<span class="rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white">{{ $thread['unread'] }}</span>@endif
                        </span>
                        <span class="block truncate text-xs text-slate-500">{{ $isStudent ? ($thread['partner_id'] === 0 ? 'Read only history' : ($person?->role === 'faculty' ? 'Instructor' : 'Administration')) : $thread['student']->student_number }}</span>
                    </span>
                </a>
            @empty
                <p class="p-5 text-sm text-slate-500">No conversations yet. Choose New chat to start one.</p>
            @endforelse
            @if($threadPages && $threadPages->hasPages())
                <div class="p-3">{{ $threadPages->links() }}</div>
            @endif
        </div>
    </aside>

    <section class="flex min-w-0 flex-1 flex-col">
        @if($selectedStudent && $selectedPartner !== null)
            @php($title = $selectedPartner === 0 ? 'Previous administrative support' : ($isStudent ? ($selectedRecipient?->name ?? 'Former staff account') : $selectedStudent->user->name))
            <header class="flex items-center gap-3 border-b border-slate-200 px-4 py-4">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-[#123A63] font-bold text-white">{{ strtoupper(substr($title, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <h2 class="truncate font-bold text-slate-900">{{ $title }}</h2>
                    <p class="truncate text-xs text-slate-500">{{ $selectedPartner === 0 ? 'Earlier shared conversation · read only' : ($isStudent ? ($selectedRecipient?->role === 'faculty' ? 'Instructor' : 'Administration') : $selectedStudent->student_number) }}</p>
                </div>
                <div class="flex gap-2">
                    <form method="post" action="{{ route('messages.archive', ['student' => $selectedStudent, 'partner' => $selectedPartner]) }}">@csrf @method('PATCH')
                        <button class="rounded-lg border px-2 py-2 text-xs font-semibold text-slate-700">{{ $state?->archived_at ? 'Restore' : 'Archive' }}</button>
                    </form>
                    <form method="post" action="{{ route('messages.conversation.destroy', ['student' => $selectedStudent, 'partner' => $selectedPartner]) }}" onsubmit="return confirm('Delete this conversation from your account? The other person keeps their copy.')">@csrf @method('DELETE')
                        <button class="rounded-lg border border-red-300 px-2 py-2 text-xs font-semibold text-red-700">Delete</button>
                    </form>
                </div>
            </header>
            @if($hasEarlierMessages)<button id="load-earlier-messages" type="button" class="border-b border-slate-200 bg-white py-2 text-xs font-semibold text-[#123A63]">Load earlier messages</button>@endif
            <div id="message-thread" class="flex-1 space-y-3 overflow-y-auto bg-slate-50 p-5" aria-live="polite">
                @forelse($messages as $message)
                    <div data-message-id="{{ $message->id }}" class="flex {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[78%] rounded-2xl px-4 py-3 {{ $message->unsent_at ? 'border border-slate-200 bg-slate-100 text-slate-500' : ($message->sender_id === auth()->id() ? 'rounded-br-md bg-[#123A63] text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-800') }}">
                            <p class="whitespace-pre-wrap break-words text-sm {{ $message->unsent_at ? 'italic' : '' }}">{{ $message->unsent_at ? 'This message was unsent.' : $message->body }}</p>
                            <p class="mt-1 text-[11px] {{ $message->sender_id === auth()->id() ? 'text-blue-100' : 'text-slate-500' }}">{{ $message->sender->name }} · {{ $message->created_at->format('M j, g:i A') }}{{ $message->edited_at && ! $message->unsent_at ? ' · Edited' : '' }}</p>
                            @if($message->sender_id === auth()->id() && ! $message->unsent_at)
                                <div class="mt-2 flex justify-end gap-3 text-xs"><button type="button" data-edit-message="{{ $message->id }}" class="underline">Edit</button><button type="button" data-unsend-message="{{ $message->id }}" class="underline">Unsend</button></div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div id="empty-thread" class="grid h-full place-items-center text-center"><p class="text-sm text-slate-500">No messages yet. Send a message to begin.</p></div>
                @endforelse
            </div>
            @if($canSend)
                <form id="message-form" class="flex items-end gap-3 border-t border-slate-200 bg-white p-4">
                    <textarea id="message-body" rows="1" maxlength="4000" required placeholder="Type a message..." class="max-h-32 min-h-11 resize-none"></textarea>
                    <button id="message-send" class="h-11 shrink-0 rounded-xl bg-[#123A63] px-5 font-semibold text-white">Send</button>
                </form>
            @else
                <p class="border-t border-slate-200 p-4 text-center text-sm text-slate-500">This conversation is available for reading only.</p>
            @endif
        @else
            <div class="grid h-full place-items-center p-8 text-center"><div><p class="text-xl font-bold text-slate-800">No conversation selected</p><p class="mt-2 text-slate-500">Choose New chat to start messaging.</p></div></div>
        @endif
    </section>
</div>

<dialog id="new-chat-dialog" class="m-auto w-[min(32rem,calc(100%-2rem))] rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
    <div class="p-6">
        <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-bold text-slate-900">New chat</h2><p class="mt-1 text-sm text-slate-500">{{ $isStudent ? 'Choose one of your instructors or an administrator.' : 'Choose a student.' }}</p></div><button type="button" onclick="document.getElementById('new-chat-dialog').close()" class="text-2xl leading-none text-slate-400" aria-label="Close">&times;</button></div>
        @if($isStudent)
            <input id="new-chat-search" class="mt-5" placeholder="Search by name..." autofocus>
        @else
            <form method="get" action="{{ route(request()->routeIs('messages.archived') ? 'messages.archived' : 'messages.index') }}" class="mt-5 flex gap-2">
                <input name="student_search" value="{{ request('student_search') }}" class="min-w-0 flex-1" placeholder="Search name or student number..." autofocus>
                <button class="rounded-lg bg-[#123A63] px-4 py-2 text-sm font-semibold text-white">Search</button>
            </form>
        @endif
        <div id="new-chat-options" class="mt-4 max-h-96 overflow-y-auto rounded-xl border border-slate-200">
            @if($isStudent)
                @forelse($availableRecipients as $recipient)
                        <form method="post" action="{{ route('messages.recipient.select', $recipient) }}" data-chat-search="{{ strtolower($recipient->name.' '.$recipient->role) }}" class="border-b border-slate-100 last:border-b-0">@csrf
                            <button class="flex w-full flex-col p-3 text-left hover:bg-blue-50"><strong class="text-sm text-slate-900">{{ $recipient->name }}</strong><span class="text-xs text-slate-500">{{ $recipient->role === 'faculty' ? 'Instructor' : 'Administration' }}</span></button>
                        </form>
                @empty
                    <p class="p-5 text-center text-sm text-slate-500">No available instructors or administrators.</p>
                @endforelse
            @else
                @forelse($allStudents as $student)
                    <form method="post" action="{{ route('messages.select', $student) }}" data-chat-search="{{ strtolower($student->user->name.' '.$student->student_number) }}" class="border-b border-slate-100 last:border-b-0">@csrf
                        <button class="flex w-full flex-col p-3 text-left hover:bg-blue-50"><strong class="text-sm text-slate-900">{{ $student->user->name }}</strong><span class="text-xs text-slate-500">{{ $student->student_number }} · {{ $student->course?->code }}</span></button>
                    </form>
                @empty
                    <p class="p-5 text-center text-sm text-slate-500">No eligible students.</p>
                @endforelse
            @endif
        </div>
        @if(!$isStudent && $allStudents->hasPages())
            <div class="mt-4">{{ $allStudents->links() }}</div>
        @endif
        @if($isStudent)<p id="new-chat-empty" class="mt-4 hidden text-center text-sm text-slate-500">No matching people found.</p>@endif
    </div>
</dialog>

@if($selectedStudent && $selectedPartner !== null)
<script>
(() => {
    const thread = document.getElementById('message-thread');
    const form = document.getElementById('message-form');
    const body = document.getElementById('message-body');
    const send = document.getElementById('message-send');
    const updateUrl = @json(route('messages.update', ['message' => '__MESSAGE__']));
    const unsendUrl = @json(route('messages.destroy', ['message' => '__MESSAGE__']));
    const storeUrl = @json(route('messages.store', ['student' => $selectedStudent, 'partner' => $selectedPartner]));
    const pollUrl = @json(route('messages.updates', ['student' => $selectedStudent, 'partner' => $selectedPartner]));
    const historyUrl = @json(route('messages.history', ['student' => $selectedStudent, 'partner' => $selectedPartner]));
    const earlierButton = document.getElementById('load-earlier-messages');
    let lastId = Number(thread.querySelector('[data-message-id]:last-of-type')?.dataset.messageId || 0);
    let oldestId = Number(thread.querySelector('[data-message-id]')?.dataset.messageId || 0);
    let lastSync = @json($messageSyncAt);
    let changedAfter = 0;
    let pendingSyncAt = null;
    let polling = false;
    let moreWaiting = false;

    function renderMessage(message) {
        document.getElementById('empty-thread')?.remove();
        let row = thread.querySelector(`[data-message-id="${message.id}"]`);
        if (!row) { row = document.createElement('div'); row.dataset.messageId = message.id; thread.append(row); }
        row.replaceChildren();
        row.className = `flex ${message.mine ? 'justify-end' : 'justify-start'}`;
        const bubble = document.createElement('div');
        bubble.className = `max-w-[78%] rounded-2xl px-4 py-3 ${message.unsent ? 'border border-slate-200 bg-slate-100 text-slate-500' : (message.mine ? 'rounded-br-md bg-[#123A63] text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-800')}`;
        const text = document.createElement('p'); text.className = 'whitespace-pre-wrap break-words text-sm'; text.textContent = message.body;
        const meta = document.createElement('p'); meta.className = 'mt-1 text-[11px]'; meta.textContent = `${message.sender} · ${message.time}${message.edited && !message.unsent ? ' · Edited' : ''}`;
        bubble.append(text, meta);
        if (message.mine && !message.unsent) {
            const actions = document.createElement('div'); actions.className = 'mt-2 flex justify-end gap-3 text-xs';
            const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'underline'; edit.textContent = 'Edit'; edit.dataset.editMessage = message.id;
            const unsend = document.createElement('button'); unsend.type = 'button'; unsend.className = 'underline'; unsend.textContent = 'Unsend'; unsend.dataset.unsendMessage = message.id;
            actions.append(edit, unsend); bubble.append(actions);
        }
        row.append(bubble);
        lastId = Math.max(lastId, Number(message.id));
        if (!oldestId || Number(message.id) < oldestId) oldestId = Number(message.id);
    }

    earlierButton?.addEventListener('click', async () => {
        earlierButton.disabled = true;
        const previousHeight = thread.scrollHeight;
        try {
            const response = await fetch(`${historyUrl}?before=${oldestId}`, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('History unavailable');
            const data = await response.json();
            data.messages.reverse().forEach(message => {
                renderMessage(message);
                thread.prepend(thread.querySelector(`[data-message-id="${message.id}"]`));
            });
            thread.scrollTop += thread.scrollHeight - previousHeight;
            if (!data.has_more) earlierButton.remove();
        } catch (error) { alert('Older messages could not be loaded. Try again.'); }
        finally { earlierButton.disabled = false; }
    });

    async function poll() {
        if (document.hidden || polling) return;
        polling = true;
        moreWaiting = false;
        try {
            const response = await fetch(`${pollUrl}?after=${lastId}&since=${encodeURIComponent(lastSync)}&changed_after=${changedAfter}`, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (response.status === 401) return window.handleSessionExpiry(response);
            if (response.ok) { const data = await response.json(); data.messages.forEach(message => {
                if (Number(message.id) > lastId || thread.querySelector(`[data-message-id="${message.id}"]`)) renderMessage(message);
            });
                if (data.has_more_changes) {
                    pendingSyncAt ??= data.sync_at;
                    changedAfter = data.changed_after;
                } else {
                    lastSync = pendingSyncAt ?? data.sync_at;
                    pendingSyncAt = null;
                    changedAfter = 0;
                }
                moreWaiting = data.has_more_changes || data.has_more_new;
            }
        } catch (error) {} finally {
            polling = false;
            if (moreWaiting) setTimeout(poll, 100);
        }
    }

    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (!body.value.trim()) return;
        send.disabled = true;
        try {
            const response = await fetch(storeUrl, {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}, body: JSON.stringify({body: body.value})});
            if (response.ok) { renderMessage((await response.json()).message); body.value = ''; thread.scrollTop = thread.scrollHeight; }
            else alert('Message could not be sent. Refresh this conversation and try again.');
        } finally { send.disabled = false; body.focus(); }
    });
    body?.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); } });
    thread.addEventListener('click', async event => {
        const edit = event.target.closest('[data-edit-message]');
        const unsend = event.target.closest('[data-unsend-message]');
        const id = edit?.dataset.editMessage || unsend?.dataset.unsendMessage;
        if (!id) return;
        if (edit) {
            const current = edit.closest('[data-message-id]').querySelector('p').textContent;
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
    setInterval(poll, 10000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
</script>
@endif
<script>
@if($isStudent)
document.getElementById('new-chat-search').addEventListener('input', event => {
    const query = event.target.value.toLowerCase().trim();
    let count = 0;
    document.querySelectorAll('#new-chat-options [data-chat-search]').forEach(item => {
        const visible = item.dataset.chatSearch.includes(query);
        item.classList.toggle('hidden', !visible);
        if (visible) count++;
    });
    document.getElementById('new-chat-empty').classList.toggle('hidden', count > 0);
});
@elseif(request()->filled('student_search') || request()->has('student_page'))
document.getElementById('new-chat-dialog').showModal();
@endif
</script>
@endsection
