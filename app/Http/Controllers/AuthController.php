<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Services\BarberAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        return redirect()->route('barbers.index', ['add' => 1]);
    }

    public function emailPreview(Request $request, BarberAccountService $accounts)
    {
        $data = $request->validate(['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100']]);

        return response()->json(['email' => $accounts->availableEmail($data['first_name'], $data['last_name'])]);
    }

    public function register(Request $request, BarberAccountService $accounts)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'in:barber'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $accounts->create($data);

        return redirect()->route('barbers.index')->with('status', 'Barber account created. The generated email is shown in the team list.');
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

        if ($request->user()->role === 'barber' && ! Barber::current()->where('user_id', $request->user()->id)->where('status', '!=', 'inactive')->exists()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This barber account is inactive. Contact the administrator.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->update(['last_login_at' => now()]);

        // A fresh sign-in always starts at the role-specific dashboard.
        $request->session()->forget(['url.intended', 'auth.last_page']);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out securely.');
    }
}
