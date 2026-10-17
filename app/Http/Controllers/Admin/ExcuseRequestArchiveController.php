<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExcuseRequestArchive;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcuseRequestArchiveController extends Controller
{
    public function index()
    {
        $archives = ExcuseRequestArchive::latest()->paginate(20);
        return view('admin.request-archives.index', compact('archives'));
    }

    public function export(ExcuseRequestArchive $archive)
    {
        return response()->json([
            'archive_id' => $archive->archive_id,
            'archived_at' => $archive->created_at,
            'archived_by' => $archive->archived_by_name,
            'records' => $archive->records,
            'files' => $archive->files,
        ])->header('Content-Disposition', 'attachment; filename="request-archive-'.$archive->archive_id.'.json"')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function file(ExcuseRequestArchive $archive, int $file): StreamedResponse
    {
        $entry = $archive->files[$file] ?? null;
        $allowedPrefix = isset($entry['source_account_archive_id'])
            ? 'account-archives/'.$entry['source_account_archive_id'].'/'
            : "excuse-request-archives/{$archive->archive_id}/";
        abort_unless($entry && $entry['archive_path']
            && str_starts_with($entry['archive_path'], $allowedPrefix)
            && Storage::disk('local')->exists($entry['archive_path']), 404);
        return Storage::disk('local')->download($entry['archive_path'], $entry['original_name'], [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
