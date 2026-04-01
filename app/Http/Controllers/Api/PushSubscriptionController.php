<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Enregistrer ou mettre à jour un abonnement push.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint'   => 'required|string',
            'public_key' => 'required|string',
            'auth_token' => 'required|string',
        ]);

        $user = $request->user();

        // Upsert basé sur l'endpoint (unique par device/browser)
        PushSubscription::updateOrCreate(
            ['endpoint' => $request->endpoint],
            [
                'user_id'    => $user->id,
                'public_key' => $request->public_key,
                'auth_token' => $request->auth_token,
            ]
        );

        return response()->json(['status' => 'subscribed']);
    }

    /**
     * Supprimer un abonnement push.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string']);

        PushSubscription::where('endpoint', $request->endpoint)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['status' => 'unsubscribed']);
    }

    /**
     * Retourner la clé publique VAPID pour le frontend.
     */
    public function vapidKey(): JsonResponse
    {
        return response()->json(['public_key' => env('VAPID_PUBLIC_KEY')]);
    }
}
