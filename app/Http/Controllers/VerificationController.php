<?php

namespace App\Http\Controllers;

use App\Models\ExcuseRequest;
use App\Models\ExcuseRequestArchive;

class VerificationController extends Controller
{
    private const VERIFIABLE_STATUSES = ['approved', 'acknowledged', 'completed'];

    public function show(string $reference)
    {
        $item = ExcuseRequest::with(['student.user', 'subject', 'facilitator.user', 'subjects', 'facilitators.user', 'documents', 'slipIssuance', 'slipAmendments'])
            ->where(fn ($query) => $query
                ->where('reference_number', $reference)
                ->orWhere('legacy_reference_number', $reference))
            ->where(fn ($query) => $query->whereIn('status', self::VERIFIABLE_STATUSES)
                ->orWhereNotNull('approved_at'))
            ->first();

        if (! $item) {
            $archive = ExcuseRequestArchive::where(fn ($query) => $query
                ->where('reference_number', $reference)
                ->orWhere('legacy_reference_number', $reference))
                ->first();
            if ($archive && (in_array($archive->status, self::VERIFIABLE_STATUSES, true)
                || ! empty($archive->records['request']['approved_at']))) {
                return view('verify-archived', compact('archive', 'reference'));
            }

            $fallback = auth()->check()
                ? (auth()->user()->role === 'student' ? route('requests.index') : route('dashboard'))
                : route('login');

            return redirect()->to($fallback)
                ->with('error', "Invalid slip: No official record was found for reference '{$reference}'.");
        }

        return view('verify', compact('item', 'reference'));
    }
}
