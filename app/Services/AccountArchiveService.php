<?php

namespace App\Services;

use App\Models\AccountArchive;
use App\Models\ExcuseRequestArchive;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AccountArchiveService
{
    /**
     * Capture the account and every related record that could disappear or lose attribution.
     * Database constraints may require retaining the live account; in that case disable it.
     */
    public function archiveAndRemove(User $account, User $actor): AccountArchive
    {
        return $this->archiveManyAndRemove(collect([$account]), $actor)->first();
    }

    public function archiveManyAndRemove($accounts, User $actor)
    {
        $directories = [];
        $deletedStudentFiles = [];

        try {
            $archives = DB::transaction(function () use ($accounts, $actor, &$directories, &$deletedStudentFiles) {
                $result = collect();
                foreach ($accounts as $account) {
                $archiveId = (string) Str::uuid();
                $directory = "account-archives/{$archiveId}";
                $directories[] = $directory;
                $locked = User::query()->lockForUpdate()->findOrFail($account->id);
                $records = $this->snapshot($locked);
                [$files, $originals] = $this->copyFiles($records, $directory);

                $archive = AccountArchive::create([
                    'archive_id' => $archiveId,
                    'original_user_id' => $locked->id,
                    'account_name' => $locked->name,
                    'account_email' => $locked->email,
                    'account_role' => $locked->role,
                    'archived_by_id' => $actor->id,
                    'archived_by_name' => $actor->name,
                    'outcome' => 'deleted',
                    'records' => $records,
                    'files' => $files,
                ]);

                if ($records['student']) {
                    $this->archiveOwnedRequests($records, $files, $actor, $archiveId);
                }

                try {
                    $locked->delete();
                } catch (QueryException) {
                    // A referenced faculty member or recipient may still be needed by live records.
                    $locked->forceFill(['is_active' => false])->save();
                    $archive->update(['outcome' => 'deactivated']);
                }
                if ($archive->outcome === 'deleted') {
                    array_push($deletedStudentFiles, ...$originals);
                }
                $result->push($archive);
                }
                return $result;
            });
        } catch (\Throwable $error) {
            foreach ($directories as $directory) Storage::disk('local')->deleteDirectory($directory);
            throw $error;
        }

        foreach ($deletedStudentFiles as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        return $archives;
    }

    private function snapshot(User $user): array
    {
        $student = DB::table('students')->where('user_id', $user->id)->first();
        $faculty = DB::table('faculty')->where('user_id', $user->id)->first();
        $studentId = $student?->id;
        $facultyId = $faculty?->id;

        $assignments = $facultyId ? $this->rows('instructor_assignments', fn ($query) => $query->where('faculty_id', $facultyId)) : [];
        $assignmentIds = array_column($assignments, 'id');
        $requests = $this->rows('excuse_requests', function ($query) use ($studentId, $facultyId, $user) {
            $query->where(function ($related) use ($studentId, $facultyId, $user) {
                $related->where('reviewed_by', $user->id);
                if ($studentId) $related->orWhere('student_id', $studentId);
                if ($facultyId) {
                    $related->orWhere('facilitator_id', $facultyId)
                        ->orWhereIn('id', DB::table('excuse_request_subject')->select('excuse_request_id')->where('facilitator_id', $facultyId));
                }
                $related->orWhereIn('id', DB::table('request_status_histories')->select('excuse_request_id')->where('action_by', $user->id))
                    ->orWhereIn('id', DB::table('program_head_reviews')->select('excuse_request_id')->where('reviewer_id', $user->id));
                if ($facultyId) {
                    $related->orWhereIn('id', DB::table('instructor_acknowledgments')->select('excuse_request_id')->where('faculty_id', $facultyId));
                }
            });
        });
        $requestIds = array_column($requests, 'id');
        $reports = $this->rows('daily_absence_reports', function ($query) use ($studentId, $assignmentIds, $user) {
            $query->where(function ($related) use ($studentId, $assignmentIds, $user) {
                $related->where('adviser_commented_by', $user->id)->orWhere('adviser_action_by', $user->id);
                if ($studentId) $related->orWhere('student_id', $studentId);
                if ($assignmentIds) $related->orWhereIn('instructor_assignment_id', $assignmentIds);
            });
        });
        $messages = $this->rows('messages', function ($query) use ($studentId, $user) {
            $query->where(function ($related) use ($studentId, $user) {
                $related->where('sender_id', $user->id)->orWhere('recipient_id', $user->id);
                if ($studentId) $related->orWhere('student_id', $studentId);
            });
        });
        $states = $this->rows('message_conversation_states', function ($query) use ($studentId, $user) {
            $query->where(function ($related) use ($studentId, $user) {
                $related->where('user_id', $user->id)->orWhere('partner_id', $user->id);
                if ($studentId) $related->orWhere('student_id', $studentId);
            });
        });

        $records = [
            'user' => $this->withoutSecrets((array) DB::table('users')->find($user->id)),
            'student' => $student ? (array) $student : null,
            'faculty' => $faculty ? (array) $faculty : null,
            'student_subject' => $studentId ? $this->rows('student_subject', fn ($query) => $query->where('student_id', $studentId)) : [],
            'excuse_requests' => $requests,
            'excuse_request_subject' => $this->rows('excuse_request_subject', fn ($query) => $query->whereIn('excuse_request_id', $requestIds)),
            'supporting_documents' => $this->rows('supporting_documents', fn ($query) => $query->whereIn('excuse_request_id', $requestIds)),
            'request_status_histories' => $this->rows('request_status_histories', fn ($query) => $query->whereIn('excuse_request_id', $requestIds)->orWhere('action_by', $user->id)),
            'slip_issuances' => $this->rows('slip_issuances', fn ($query) => $query->whereIn('excuse_request_id', $requestIds)),
            'slip_amendments' => $this->rows('slip_amendments', fn ($query) => $query->whereIn('excuse_request_id', $requestIds)->orWhere('action_by', $user->id)),
            'program_head_reviews' => $this->rows('program_head_reviews', fn ($query) => $query->whereIn('excuse_request_id', $requestIds)->orWhere('reviewer_id', $user->id)),
            'instructor_acknowledgments' => $this->rows('instructor_acknowledgments', function ($query) use ($requestIds, $facultyId) {
                $query->whereIn('excuse_request_id', $requestIds);
                if ($facultyId) $query->orWhere('faculty_id', $facultyId);
            }),
            'instructor_assignments' => $assignments,
            'daily_absence_reports' => $reports,
            'messages' => $messages,
            'message_conversation_states' => $states,
        ];

        // Keep labels alongside foreign keys so the snapshot remains readable if catalogs change.
        $reportAssignments = $this->rows('instructor_assignments', fn ($query) => $query->whereIn('id', array_column($reports, 'instructor_assignment_id')));
        $records['instructor_assignments_context'] = array_values(array_filter($reportAssignments,
            fn ($assignment) => ! in_array($assignment['id'], $assignmentIds, true)));
        $contextStudentIds = array_unique(array_filter(array_merge(
            array_column($requests, 'student_id'),
            array_column($reports, 'student_id'),
            array_column($messages, 'student_id'),
            array_column($states, 'student_id')
        )));
        $records['students_context'] = $this->rows('students', fn ($query) => $query->whereIn('id', $contextStudentIds));
        $contextFacultyIds = array_unique(array_filter(array_merge(
            array_column($requests, 'facilitator_id'),
            array_column($records['excuse_request_subject'], 'facilitator_id'),
            array_column($records['instructor_acknowledgments'], 'faculty_id'),
            array_column($reportAssignments, 'faculty_id')
        )));
        $records['faculty_context'] = $this->rows('faculty', fn ($query) => $query->whereIn('id', $contextFacultyIds));
        $contextUserIds = array_unique(array_filter(array_merge(
            array_column($records['students_context'], 'user_id'),
            array_column($records['faculty_context'], 'user_id'),
            array_column($messages, 'sender_id'),
            array_column($messages, 'recipient_id'),
            array_column($records['request_status_histories'], 'action_by'),
            array_column($records['program_head_reviews'], 'reviewer_id'),
            array_column($reports, 'adviser_commented_by'),
            array_column($reports, 'adviser_action_by')
        )));
        $records['users_context'] = array_map(fn ($person) => $this->withoutSecrets($person),
            $this->rows('users', fn ($query) => $query->whereIn('id', $contextUserIds)));
        $subjectIds = array_unique(array_filter(array_merge(
            array_column($requests, 'subject_id'),
            array_column($assignments, 'subject_id'),
            array_column($reportAssignments, 'subject_id'),
            array_column($records['student_subject'], 'subject_id'),
            array_column($records['excuse_request_subject'], 'subject_id')
        )));
        $courseIds = array_unique(array_filter(array_merge(
            [$student?->course_id], array_column($assignments, 'course_id'),
            array_column($reportAssignments, 'course_id'),
            array_column($records['students_context'], 'course_id')
        )));
        $sectionIds = array_unique(array_filter(array_merge(
            [$student?->section_id], array_column($assignments, 'section_id'),
            array_column($reportAssignments, 'section_id'),
            array_column($records['students_context'], 'section_id')
        )));
        $records['subjects'] = $this->rows('subjects', fn ($query) => $query->whereIn('id', $subjectIds));
        $records['courses'] = $this->rows('courses', fn ($query) => $query->whereIn('id', $courseIds));
        $records['sections'] = $this->rows('sections', fn ($query) => $query->whereIn('id', $sectionIds));
        $records['academic_years'] = $this->rows('academic_years', fn ($query) => $query->whereIn('id', array_column($requests, 'academic_year_id')));
        $records['semesters'] = $this->rows('semesters', fn ($query) => $query->whereIn('id', array_column($requests, 'semester_id')));
        $records['reason_categories'] = $this->rows('reason_categories', fn ($query) => $query->whereIn('id', array_column($requests, 'reason_category_id')));

        return $records;
    }

    private function copyFiles(array $records, string $directory): array
    {
        $sources = [];
        if ($path = $records['student']['assessment_form_path'] ?? null) {
            $sources[] = ['disk' => 'local', 'path' => $path, 'kind' => 'assessment_form', 'record_id' => $records['student']['id']];
        }
        $ownedRequestIds = array_column(array_filter($records['excuse_requests'],
            fn ($request) => ($request['student_id'] ?? null) === ($records['student']['id'] ?? null)), 'id');
        foreach ($records['supporting_documents'] as $document) {
            $sources[] = [
                'disk' => $document['disk'], 'path' => $document['path'],
                'kind' => 'supporting_document', 'record_id' => $document['id'],
                'delete_original' => in_array($document['excuse_request_id'], $ownedRequestIds, true),
            ];
        }

        $manifest = [];
        $deleteOriginals = [];
        foreach ($sources as $index => $source) {
            $disk = Storage::disk($source['disk']);
            $copy = null;
            $present = $disk->exists($source['path']);
            if ($present) {
                $extension = pathinfo($source['path'], PATHINFO_EXTENSION);
                $copy = "{$directory}/".($index + 1).($extension ? ".{$extension}" : '');
                $stream = $disk->readStream($source['path']);
                if ($stream === false) throw new RuntimeException('Unable to read a file for the account archive.');
                try {
                    if (! Storage::disk('local')->put($copy, $stream)) {
                        throw new RuntimeException('Unable to save a file in the account archive.');
                    }
                } finally {
                    if (is_resource($stream)) fclose($stream);
                }
                if ($source['kind'] === 'assessment_form' || ($source['delete_original'] ?? false)) {
                    $deleteOriginals[] = [$source['disk'], $source['path']];
                }
            }
            $manifest[] = [
                'kind' => $source['kind'], 'record_id' => $source['record_id'],
                'original_disk' => $source['disk'], 'original_path' => $source['path'],
                'archive_path' => $copy, 'available_at_archive' => $present,
            ];
        }

        return [$manifest, $deleteOriginals];
    }

    private function archiveOwnedRequests(array $records, array $files, User $actor, string $accountArchiveId): void
    {
        $studentId = $records['student']['id'];
        foreach ($records['excuse_requests'] as $request) {
            if ($request['student_id'] !== $studentId) continue;
            $requestId = $request['id'];
            $related = fn ($table) => array_values(array_filter($records[$table],
                fn ($row) => $row['excuse_request_id'] === $requestId));
            $documents = $related('supporting_documents');
            $archiveFiles = [];
            foreach ($documents as $document) {
                $saved = collect($files)->first(fn ($file) => $file['kind'] === 'supporting_document' && $file['record_id'] === $document['id']);
                $archiveFiles[] = [
                    'record_id' => $document['id'], 'original_name' => $document['original_name'],
                    'original_disk' => $document['disk'], 'original_path' => $document['path'],
                    'archive_path' => $saved['archive_path'] ?? null,
                    'source_account_archive_id' => $accountArchiveId,
                    'available_at_archive' => $saved['available_at_archive'] ?? false,
                ];
            }
            $snapshot = [
                'request' => $request, 'student' => $records['student'], 'student_user' => $records['user'],
                'subject' => collect($records['subjects'])->firstWhere('id', $request['subject_id']),
                'facilitator' => collect($records['faculty_context'])->firstWhere('id', $request['facilitator_id']),
                'academic_year' => collect($records['academic_years'])->firstWhere('id', $request['academic_year_id']),
                'semester' => collect($records['semesters'])->firstWhere('id', $request['semester_id']),
                'reason_category' => collect($records['reason_categories'])->firstWhere('id', $request['reason_category_id']),
                'excuse_request_subject' => $related('excuse_request_subject'),
                'supporting_documents' => $documents,
                'request_status_histories' => $related('request_status_histories'),
                'slip_issuance' => $related('slip_issuances')[0] ?? null,
                'slip_amendments' => $related('slip_amendments'),
                'program_head_reviews' => $related('program_head_reviews'),
                'instructor_acknowledgments' => $related('instructor_acknowledgments'),
            ];
            ExcuseRequestArchive::create([
                'archive_id' => (string) Str::uuid(), 'original_request_id' => $requestId,
                'reference_number' => $request['reference_number'],
                'legacy_reference_number' => $request['legacy_reference_number'] ?? null,
                'status' => $request['status'], 'archived_by_id' => $actor->id,
                'archived_by_name' => $actor->name, 'records' => $snapshot, 'files' => $archiveFiles,
            ]);
        }
    }

    private function rows(string $table, callable $filter): array
    {
        $query = DB::table($table);
        $filter($query);
        return $query->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }

    private function withoutSecrets(array $user): array
    {
        return array_diff_key($user, array_flip([
            'password', 'remember_token', 'email_verification_otp',
            'email_verification_otp_expires_at',
        ]));
    }
}
