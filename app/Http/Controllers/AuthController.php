<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Nombre de tentatives autorisées avant verrouillage temporaire.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Durée du verrouillage, en secondes.
     */
    private const DECAY_SECONDS = 60;

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

        $throttleKey = $this->throttleKey($request, $identifier);
        $this->ensureIsNotRateLimited($throttleKey);

        $authenticated = Auth::attempt(['name' => $identifier, 'password' => $password], $remember)
                      || Auth::attempt(['email' => $identifier, 'password' => $password], $remember);

        if ($authenticated) {
            // Un compte désactivé ne doit jamais obtenir de session : on coupe
            // avant la régénération et on révoque ses jetons d'API existants.
            if (!Auth::user()->is_active) {
                Auth::user()->tokens()->delete();
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

                return back()->withErrors(['name' => 'Votre compte est désactivé.']);
            }

            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        return back()->withErrors([
            'name' => 'Nom d\'utilisateur ou mot de passe incorrect.',
        ])->onlyInput('name');
    }

    /**
     * Clé de comptage des tentatives : identifiant + IP, pour ne pas permettre
     * le verrouillage du compte d'autrui depuis une IP tierce.
     */
    private function throttleKey(Request $request, string $identifier): string
    {
        return Str::transliterate(Str::lower($identifier)) . '|' . $request->ip();
    }

    private function ensureIsNotRateLimited(string $throttleKey): void
    {
        if (!RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($throttleKey);

        throw ValidationException::withMessages([
            'name' => "Trop de tentatives de connexion. Réessayez dans {$seconds} seconde" . ($seconds > 1 ? 's' : '') . '.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
