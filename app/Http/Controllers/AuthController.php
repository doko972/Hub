<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string'],
            'password' => ['required'],
        ], [
            'name.required'     => 'Le nom d\'utilisateur est obligatoire.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $remember  = $request->boolean('remember');
        $identifier = $request->input('name');
        $password   = $request->input('password');

        $authenticated = Auth::attempt(['name' => $identifier, 'password' => $password], $remember)
                      || Auth::attempt(['email' => $identifier, 'password' => $password], $remember);

        if ($authenticated) {
            $request->session()->regenerate();

            if (!Auth::user()->is_active) {
                Auth::logout();
                return back()->withErrors(['name' => 'Votre compte est désactivé.']);
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'name' => 'Nom d\'utilisateur ou mot de passe incorrect.',
        ])->onlyInput('name');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
