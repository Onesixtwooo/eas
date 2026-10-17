<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvalidateUserSessions
{
    public function __invoke(User $user, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $sessions = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey());

        if ($exceptSessionId !== null) {
            $sessions->where('id', '!=', $exceptSessionId);
        }

        $sessions->delete();
    }
}
