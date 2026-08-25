<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccessModeController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->user()->isDualRole(), 404);

        return view('auth.choose-access', ['roles' => $request->user()->assignedRoles()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in($request->user()->assignedRoles())],
        ]);
        $request->session()->put('active_role', $data['role']);

        return redirect()->route('dashboard');
    }
}
