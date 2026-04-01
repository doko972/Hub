<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;

class GoogleAuthController extends Controller
{
    /**
     * Rediriger vers Google pour l'autorisation
     */
    public function redirect(Request $request)
    {
        $request->session()->put('auth_token', $request->query('token'));

        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/calendar.events'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    /**
     * Callback après autorisation Google
     */
    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = Auth::user();

            if (!$user) {
                $token = $request->session()->get('auth_token');
                if ($token && str_contains($token, '|')) {
                    [$id, $rawToken] = explode('|', $token, 2);
                    $user = \App\Models\User::where('id', function ($query) use ($id, $rawToken) {
                        $query->select('tokenable_id')
                            ->from('personal_access_tokens')
                            ->where('id', $id)
                            ->where('token', hash('sha256', $rawToken))
                            ->limit(1);
                    })->first();
                }
            }

            if (!$user) {
                return redirect()->route('login')->with('error', 'Utilisateur non trouvé');
            }

            $user->update([
                'google_id'                => $googleUser->getId(),
                'google_access_token'      => $googleUser->token,
                'google_refresh_token'     => $googleUser->refreshToken,
                'google_token_expires_at'  => now()->addSeconds($googleUser->expiresIn),
            ]);

            return view('google.success');

        } catch (\Exception $e) {
            return redirect()->route('cortex.chat')->with('error', 'Erreur de connexion Google : ' . $e->getMessage());
        }
    }

    /**
     * Déconnecter Google Calendar
     */
    public function disconnect(Request $request)
    {
        $request->user()->update([
            'google_id'               => null,
            'google_access_token'     => null,
            'google_refresh_token'    => null,
            'google_token_expires_at' => null,
        ]);

        return response()->json(['message' => 'Google Calendar déconnecté']);
    }

    /**
     * Vérifier si Google est connecté
     */
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'connected'  => !empty($user->google_access_token),
            'expires_at' => $user->google_token_expires_at,
        ]);
    }
}
