<?php

namespace App\Http\Controllers;

use App\Http\Middleware\TrackSystemChanges;
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
}
