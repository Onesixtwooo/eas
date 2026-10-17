<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountArchiveController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $archives = AccountArchive::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('account_name', 'like', "%{$search}%")
                ->orWhere('account_email', 'like', "%{$search}%")
                ->orWhere('original_user_id', $search)))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.account-archives.index', compact('archives'));
    }

    public function show(Request $request, AccountArchive $archive)
    {
        $visibleRecords = $this->visibleRecords($archive, $request->user()->id);
        return view('admin.account-archives.show', compact('archive', 'visibleRecords'));
    }

    public function export(Request $request, AccountArchive $archive)
    {
        return response()->json([
            'archive_id' => $archive->archive_id,
            'archived_at' => $archive->created_at,
            'archived_by' => $archive->archived_by_name,
            'outcome' => $archive->outcome,
            'records' => $this->visibleRecords($archive, $request->user()->id),
            'files' => $archive->files,
        ])->header('Content-Disposition', 'attachment; filename="account-archive-'.$archive->archive_id.'.json"')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    private function visibleRecords(AccountArchive $archive, int $viewerId): array
    {
        $records = $archive->records;
        $records['messages'] = array_values(array_filter($records['messages'] ?? [],
            fn ($message) => $message['recipient_id'] === null
                || (int) $message['recipient_id'] === $viewerId
                || (int) $message['sender_id'] === $viewerId));
        $records['message_conversation_states'] = array_values(array_filter($records['message_conversation_states'] ?? [],
            fn ($state) => (int) $state['user_id'] === $viewerId));
        return $records;
    }

    public function file(AccountArchive $archive, int $file): StreamedResponse
    {
        $entry = $archive->files[$file] ?? null;
        abort_unless($entry && $entry['archive_path']
            && str_starts_with($entry['archive_path'], "account-archives/{$archive->archive_id}/")
            && Storage::disk('local')->exists($entry['archive_path']), 404);

        return Storage::disk('local')->download($entry['archive_path'], basename($entry['original_path']), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
