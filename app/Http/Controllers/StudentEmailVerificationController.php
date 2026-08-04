<?php

namespace App\Http\Controllers;

use App\Mail\StudentEmailVerificationCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class StudentEmailVerificationController extends Controller
{
    public function show(Request $request)
    {
        $student = $this->studentFromSession($request);

        if ($student->email_verified_at) {
            return redirect()->route('login')->with('success', 'Your email address is already verified. Please wait for administrator approval.');
        }

        return view('auth.verify-email-otp', ['email' => $student->email]);
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['otp' => ['required', 'digits:6']]);
        $student = $this->studentFromSession($request);

        if (! $student->email_verification_otp_expires_at || $student->email_verification_otp_expires_at->isPast()) {
            return back()->withErrors(['otp' => 'This verification code has expired. Request a new code.']);
        }

        if (! Hash::check($data['otp'], $student->email_verification_otp)) {
            return back()->withErrors(['otp' => 'The verification code is incorrect.']);
        }

        $student->update([
            'email_verified_at' => now(),
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => null,
        ]);
        $request->session()->forget('email_verification_user_id');

        return redirect()->route('login')->with('success', 'Email verified. Please wait for an administrator to approve your registration before signing in.');
    }

    public function resend(Request $request)
    {
        $student = $this->studentFromSession($request);
        $otp = (string) random_int(100000, 999999);

        $student->update([
            'email_verification_otp' => Hash::make($otp),
            'email_verification_otp_expires_at' => now()->addMinutes(10),
        ]);
        Mail::to($student->email)->send(new StudentEmailVerificationCode($student, $otp));

        return back()->with('success', 'A new verification code has been sent.');
    }

    private function studentFromSession(Request $request): User
    {
        $student = User::whereKey($request->session()->get('email_verification_user_id'))
            ->where('role', 'student')
            ->first();

        abort_unless($student, 404);

        return $student;
    }
}
