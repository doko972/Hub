<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PbxServer;
use App\Services\OvhMachineControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Actions sur la machine d'un centrex.
 *
 * Réservé aux administrateurs (middleware 'admin' sur la route) : la liste des
 * centrex est partagée entre tous les utilisateurs connectés, mais redémarrer
 * un PBX coupe les communications en cours d'un client. Consulter et agir ne
 * relèvent pas du même droit.
 *
 * Chaque action est tracée dans le journal d'activité : savoir quel centrex a
 * redémarré, quand et à la demande de qui n'est pas un confort.
 */
class PbxActionController extends Controller
{
    public function __construct(private readonly OvhMachineControl $ovh)
    {
    }

    public function reboot(Request $request, PbxServer $centrex): JsonResponse
    {
        if (! OvhMachineControl::isConfigured()) {
            return response()->json([
                'message' => "L'accès à l'API OVHcloud n'est pas configuré sur ce serveur.",
            ], 503);
        }

        if (! OvhMachineControl::pilotable($centrex)) {
            return response()->json([
                'message' => "« " . $centrex->name . " » n'est rattaché à aucune machine OVHcloud.",
            ], 422);
        }

        // Tracé avant l'appel : si OVH accepte puis que la réponse se perd, la
        // machine aura tout de même redémarré. Mieux vaut une trace en trop
        // qu'un redémarrage sans trace.
        ActivityLog::record('rebooted', 'Centrex', $centrex->id, $centrex->name);

        try {
            $this->ovh->redemarrer($centrex);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'message' => 'Redémarrage de « ' . $centrex->name . ' » demandé à OVHcloud.',
        ]);
    }
}
