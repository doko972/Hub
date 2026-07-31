<?php

namespace App\Http\Controllers;

use App\Services\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
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
