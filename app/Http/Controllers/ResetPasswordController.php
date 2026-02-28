<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function show(string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request()->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'email.required'      => "L'adresse email est obligatoire.",
            'email.email'         => "L'adresse email n'est pas valide.",
            'password.required'   => 'Le mot de passe est obligatoire.',
            'password.confirmed'  => 'Les mots de passe ne correspondent pas.',
            'password.min'        => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])
                     ->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.');
        }

        return back()->withErrors([
            'email' => match ($status) {
                Password::INVALID_TOKEN => 'Ce lien de réinitialisation est invalide ou a expiré.',
                Password::INVALID_USER  => "Aucun compte n'est associé à cette adresse email.",
                default                 => 'Une erreur est survenue. Veuillez réessayer.',
            },
        ]);
    }
}
