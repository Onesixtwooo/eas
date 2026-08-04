<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Models\User;
class AuthController extends Controller {
    public function login(){return view('auth.login');}
    public function authenticate(Request $r){$data=$r->validate(['email'=>'required|email','password'=>'required']);$user=User::where('email',$data['email'])->first();if($user && Hash::check($data['password'],$user->password)){if(! $user->is_active){return back()->withErrors(['email'=>'Your account is disabled, please report to the IT office.'])->onlyInput('email');}if($user->role==='student' && ! $user->email_verified_at){$r->session()->put('email_verification_user_id',$user->id);return redirect()->route('register.verify-email')->with('error','Verify your email address before signing in.');}if($user->role==='student' && ! $user->registration_verified_at){return back()->withErrors(['email'=>'Your registration is awaiting administrator verification. Please try again after your account has been confirmed.'])->onlyInput('email');}}if(Auth::attempt($data+['is_active'=>true],$r->boolean('remember'))){$r->session()->regenerate();return redirect()->intended(route('dashboard'));}return back()->withErrors(['email'=>'The credentials do not match our records.'])->onlyInput('email');}
    public function forgot(){return view('auth.forgot');}
    public function emailReset(Request $r){$r->validate(['email'=>'required|email']);$status=Password::sendResetLink($r->only('email'));return back()->with($status===Password::RESET_LINK_SENT?'success':'error',__($status));}
    public function reset(Request $r, string $token){return view('auth.reset-password',['token'=>$token,'email'=>$r->query('email')]);}
    public function updatePassword(Request $r){$data=$r->validate(['token'=>'required','email'=>'required|email','password'=>'required|string|min:8|confirmed']);$status=Password::reset($data,function(User $user,string $password){$user->forceFill(['password'=>$password,'remember_token'=>Str::random(60)])->save();});return $status===Password::PASSWORD_RESET?redirect()->route('login')->with('success',__($status)):back()->withErrors(['email'=>__($status)])->withInput($r->only('email'));}
    public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('login');}
}
