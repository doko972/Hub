<?php

namespace App\Http\Controllers;

use App\Services\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PresenceController extends Controller
{
    /**
     * Signal de vie : ne renvoie rien, le travail est fait par le middleware
     * TrackLastSeen qui met à jour la date de dernière activité.
     *
     * Existe pour les pages sans sondage (le chat IA notamment), dont les
     * utilisateurs passaient hors ligne en pleine utilisation.
     */
    public function ping(): Response
    {
        return response()->noContent();
    }

    /**
     * Liste de présence, consommée par le sondage de la sidebar.
     */
    public function index(Request $request): JsonResponse
    {
        return response()
            ->json([
                'users'  => Presence::roster($request->user()->id),
                'online' => Presence::onlineCount(),
            ])
            ->header('Cache-Control', 'no-store, private');
    }
}
