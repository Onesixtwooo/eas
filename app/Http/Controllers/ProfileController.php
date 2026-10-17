<?php

namespace App\Http\Controllers;

use App\Services\InvalidateUserSessions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load(['student.course', 'student.section', 'faculty']);

        return view('profile', compact('user'));
    }

    public function updateDetails(Request $request)
    {
        $user = $request->user();
        $data = $request->validateWithBag('details', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['prohibited'],
        ]);

        $user->update(['name' => $data['name']]);

        if ($user->faculty) {
            $user->faculty->update(['name' => $data['name']]);
        }

        return back()->with('success', 'Your profile details have been updated.');
    }

    public function updatePassword(Request $request, InvalidateUserSessions $invalidateSessions)
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        DB::transaction(function () use ($request, $data, $invalidateSessions) {
            $user = $request->user();
            $user->forceFill([
                'password' => $data['password'],
                'remember_token' => Str::random(60),
            ])->save();
            $invalidateSessions($user, $request->session()->getId());
        });

        return back()->with('success', 'Your password has been changed.');
    }
}
