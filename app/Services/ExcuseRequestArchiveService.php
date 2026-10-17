<?php

namespace App\Services;

use App\Models\ExcuseRequest;
use App\Models\ExcuseRequestArchive;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ExcuseRequestArchiveService
{
    public function archiveAndRemove(ExcuseRequest $request, User $actor): ExcuseRequestArchive
    {
        $archiveId = (string) Str::uuid();
        $directory = "excuse-request-archives/{$archiveId}";
        $originals = [];

        try {
            $archive = DB::transaction(function () use ($request, $actor, $archiveId, $directory, &$originals) {
                $locked = ExcuseRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
                $records = $this->snapshot($locked);
                $files = [];
                foreach ($records['supporting_documents'] as $index => $document) {
                    $disk = Storage::disk($document['disk']);
                    $present = $disk->exists($document['path']);
                    $copy = null;
                    if ($present) {
                        $extension = pathinfo($document['path'], PATHINFO_EXTENSION);
                        $copy = $directory.'/'.($index + 1).($extension ? '.'.$extension : '');
                        $stream = $disk->readStream($document['path']);
                        if ($stream === false) throw new RuntimeException('Unable to read a request attachment for archiving.');
                        try {
                            if (! Storage::disk('local')->put($copy, $stream)) {
                                throw new RuntimeException('Unable to save a request attachment in the archive.');
                            }
                        } finally {
                            if (is_resource($stream)) fclose($stream);
                        }
                        $originals[] = [$document['disk'], $document['path']];
                    }
                    $files[] = [
                        'record_id' => $document['id'], 'original_name' => $document['original_name'],
                        'original_disk' => $document['disk'], 'original_path' => $document['path'],
                        'archive_path' => $copy, 'available_at_archive' => $present,
                    ];
                }
                $archive = ExcuseRequestArchive::create([
                    'archive_id' => $archiveId,
                    'original_request_id' => $locked->id,
                    'reference_number' => $locked->reference_number,
                    'legacy_reference_number' => $locked->legacy_reference_number,
                    'status' => $locked->status,
                    'archived_by_id' => $actor->id,
                    'archived_by_name' => $actor->name,
                    'records' => $records,
                    'files' => $files,
                ]);
                $locked->delete();
                return $archive;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->deleteDirectory($directory);
            throw $error;
        }

        foreach ($originals as [$disk, $path]) Storage::disk($disk)->delete($path);
        return $archive;
    }

    private function snapshot(ExcuseRequest $request): array
    {
        $row = fn ($table, $id) => (array) DB::table($table)->find($id);
        $rows = fn ($table) => DB::table($table)->where('excuse_request_id', $request->id)
            ->orderBy('id')->get()->map(fn ($item) => (array) $item)->all();
        $student = $row('students', $request->student_id);
        $user = $row('users', $student['user_id']);
        $user = array_diff_key($user, array_flip(['password', 'remember_token', 'email_verification_otp', 'email_verification_otp_expires_at']));
        return [
            'request' => $row('excuse_requests', $request->id),
            'student' => $student, 'student_user' => $user,
            'subject' => $row('subjects', $request->subject_id),
            'facilitator' => $row('faculty', $request->facilitator_id),
            'covered_subjects' => DB::table('subjects')->whereIn('id', array_column($rows('excuse_request_subject'), 'subject_id'))
                ->orderBy('id')->get()->map(fn ($item) => (array) $item)->all(),
            'covered_facilitators' => DB::table('faculty')->whereIn('id', array_column($rows('excuse_request_subject'), 'facilitator_id'))
                ->orderBy('id')->get()->map(fn ($item) => (array) $item)->all(),
            'academic_year' => $row('academic_years', $request->academic_year_id),
            'semester' => $row('semesters', $request->semester_id),
            'reason_category' => $row('reason_categories', $request->reason_category_id),
            'excuse_request_subject' => $rows('excuse_request_subject'),
            'supporting_documents' => $rows('supporting_documents'),
            'request_status_histories' => $rows('request_status_histories'),
            'program_head_reviews' => $rows('program_head_reviews'),
            'instructor_acknowledgments' => $rows('instructor_acknowledgments'),
        ];
    }
}
