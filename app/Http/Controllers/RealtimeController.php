<?php

namespace App\Http\Controllers;

use App\Http\Middleware\TrackSystemChanges;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RealtimeController extends Controller
{
    public function version(): JsonResponse
    {
        return response()->json([
            'revision' => Cache::get(TrackSystemChanges::CACHE_KEY, 'initial'),
        ]);
    }

    public function presence(): JsonResponse
    {
        abort_unless(in_array(auth()->user()->role, ['admin', 'program_head'], true), 403);
        $online = Student::pluck('user_id')->filter(fn ($userId) =>
            Cache::has(TrackSystemChanges::PRESENCE_PREFIX.$userId)
        )->values();

        return response()->json(['online_user_ids' => $online]);
    }
}
