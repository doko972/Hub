<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function show()
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => "L'adresse email est obligatoire.",
            'email.email'    => "L'adresse email n'est pas valide.",
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Un lien de réinitialisation a été envoyé à votre adresse email.');
        }

        return back()->withErrors([
            'email' => match ($status) {
                Password::INVALID_USER    => "Aucun compte n'est associé à cette adresse email.",
                Password::RESET_THROTTLED => 'Veuillez patienter avant de demander un nouveau lien.',
                default                   => 'Une erreur est survenue. Veuillez réessayer.',
            },
        ]);
    }
}
