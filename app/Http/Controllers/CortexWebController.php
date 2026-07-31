<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class CortexWebController extends Controller
{
    /**
     * Marge avant expiration à partir de laquelle on renouvelle le jeton,
     * pour éviter qu'il expire pendant que la page reste ouverte.
     */
    private const RENEW_BEFORE_MINUTES = 1440; // 24 h

    /**
     * Affiche l'interface de chat Cortex Web
     */
    public function index(Request $request)
    {
        $this->ensureApiToken($request);

        return view('cortex.chat');
    }

    /**
     * Affiche une conversation spécifique
     */
    public function show(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $this->ensureApiToken($request);

        return view('cortex.chat', [
            'currentConversation' => $conversation
        ]);
    }

    /**
     * Garantit la présence en session d'un jeton d'API valide pour le front.
     *
     * Les jetons expirent désormais (config/sanctum.php) : on en émet un
     * nouveau quand celui en session approche de sa fin de vie. Les anciens ne
     * sont pas supprimés — cela couperait les autres onglets ouverts — ils sont
     * purgés par la commande planifiée sanctum:prune-expired.
     */
    private function ensureApiToken(Request $request): void
    {
        $expiresAt = $request->session()->get('api_token_expires_at');

        $stillValid = $request->session()->has('api_token')
            && $expiresAt !== null
            && now()->addMinutes(self::RENEW_BEFORE_MINUTES)->lt($expiresAt);

        if ($stillValid) {
            return;
        }

        $user       = auth()->user();
        $deviceId   = substr(hash('sha256', $request->userAgent() . $request->ip()), 0, 8);
        $expiration = config('sanctum.expiration');

        $token = $user->createToken('cortex-web-' . $deviceId)->plainTextToken;

        $request->session()->put([
            'api_token'            => $token,
            'api_token_expires_at' => $expiration ? now()->addMinutes($expiration) : now()->addYears(10),
        ]);
    }
}
