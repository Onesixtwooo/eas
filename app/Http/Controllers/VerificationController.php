<?php

namespace App\Http\Controllers;

use App\Models\ExcuseRequest;

class VerificationController extends Controller
{
    private const VERIFIABLE_STATUSES = ['approved', 'acknowledged', 'completed'];

    public function show(string $reference)
    {
        $item = ExcuseRequest::with(['student.user', 'subject', 'facilitator.user', 'subjects', 'facilitators.user'])
            ->where('reference_number', $reference)
            ->whereIn('status', self::VERIFIABLE_STATUSES)
            ->first();

        return view('verify', compact('item', 'reference'));
    }
}
