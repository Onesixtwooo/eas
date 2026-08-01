<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Models\User;
class AuthController extends Controller {
    public function login(){return view('auth.login');}
    public function authenticate(Request $r){$data=$r->validate(['email'=>'required|email','password'=>'required']);$user=User::where('email',$data['email'])->first();if($user && ! $user->is_active && Hash::check($data['password'],$user->password)){return back()->withErrors(['email'=>'Your account is disabled, please report to the IT office.'])->onlyInput('email');}if(Auth::attempt($data+['is_active'=>true],$r->boolean('remember'))){$r->session()->regenerate();return redirect()->intended(route('dashboard'));}return back()->withErrors(['email'=>'The credentials do not match our records.'])->onlyInput('email');}
    public function forgot(){return view('auth.forgot');}
    public function emailReset(Request $r){$r->validate(['email'=>'required|email']);$status=Password::sendResetLink($r->only('email'));return back()->with($status===Password::RESET_LINK_SENT?'success':'error',__($status));}
    public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('login');}
}
