<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('auth.register', ['hasAdmin' => true]);
    }

    public function register(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:barber'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        DB::transaction(function () use ($data): void {
            $user = User::create($data);

            if ($user->role === 'barber') {
                $name = preg_split('/\s+/', trim($user->name), 2);

                Barber::create([
                    'user_id' => $user->id,
                    'first_name' => mb_substr($name[0], 0, 100),
                    'last_name' => mb_substr($name[1] ?? '-', 0, 100),
                    'hired_date' => today(),
                    'status' => 'active',
                ]);
            }

        });

        return redirect()->route('barbers.index')->with('status', 'Barber account created.');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->update(['last_login_at' => now()]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
