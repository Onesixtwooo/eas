<?php

namespace App\Http\Controllers;

use App\Models\ExcuseRequest;

class VerificationController extends Controller
{
    private const VERIFIABLE_STATUSES = ['approved', 'acknowledged', 'completed'];

    public function show(string $reference)
    {
        $item = ExcuseRequest::with(['student.user', 'subject', 'facilitator.user', 'subjects', 'facilitators.user'])
            ->where(fn ($query) => $query
                ->where('reference_number', $reference)
                ->orWhere('legacy_reference_number', $reference))
            ->whereIn('status', self::VERIFIABLE_STATUSES)
            ->first();

        if (! $item) {
            $fallback = auth()->check()
                ? (auth()->user()->role === 'student' ? route('requests.index') : route('dashboard'))
                : route('login');

            $previous = url()->previous();
            $current = url()->current();

            $destination = ($previous && $previous !== $current && $previous !== url('/'))
                ? $previous
                : $fallback;

            return redirect()->to($destination)
                ->with('error', "Invalid slip: No official record was found for reference '{$reference}'.");
        }

        return view('verify', compact('item', 'reference'));
    }
}
