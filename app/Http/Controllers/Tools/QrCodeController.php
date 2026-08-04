<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\QrPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrCodeController extends Controller
{
    /**
     * Taille maximale de la charge utile une fois encodée, en octets.
     * Le formulaire tient largement dedans ; au-delà, c'est un abus.
     */
    private const MAX_PAYLOAD_BYTES = 20000;

    public function index(Request $request)
    {
        // Seuls les noms sont rendus dans la page. La charge utile — qui
        // contient les mots de passe SIP — n'est servie qu'à la demande, à
        // l'ouverture d'une configuration précise. Même principe que le coffre
        // d'identifiants du tableau de bord.
        return view('tools.qr-code', [
            'presets' => $request->user()->qrPresets()->get(['id', 'name', 'updated_at']),
        ]);
    }

    /**
     * Enregistre une configuration, ou met à jour celle qui porte le même nom.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePreset($request);

        $existante = $request->user()->qrPresets()->where('name', $validated['name'])->first();

        if (!$existante && $request->user()->qrPresets()->count() >= QrPreset::MAX_PER_USER) {
            return response()->json([
                'message' => 'Vous avez atteint ' . QrPreset::MAX_PER_USER
                    . ' configurations. Supprimez-en une avant d\'en ajouter.',
            ], 422);
        }

        $preset = $request->user()->qrPresets()->updateOrCreate(
            ['name' => $validated['name']],
            ['payload' => $validated['payload']],
        );

        return response()->json([
            'preset'  => ['id' => $preset->id, 'name' => $preset->name],
            'updated' => (bool) $existante,
        ], $existante ? 200 : 201);
    }

    /**
     * Renvoie la charge utile d'une configuration, pour recharger le formulaire.
     */
    public function show(Request $request, int $preset): JsonResponse
    {
        // Recherche restreinte aux configurations de l'utilisateur : celle d'un
        // autre renvoie 404, sans révéler son existence.
        $modele = $request->user()->qrPresets()->findOrFail($preset);

        return response()
            ->json(['name' => $modele->name, 'payload' => $modele->payload])
            ->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, int $preset): JsonResponse
    {
        $request->user()->qrPresets()->findOrFail($preset)->delete();

        return response()->json(['deleted' => $preset]);
    }

    private function validatePreset(Request $request): array
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:60'],
            'payload' => ['required', 'array'],
        ], [
            'name.required'    => 'Donnez un nom à cette configuration.',
            'payload.required' => 'Rien à enregistrer.',
        ]);

        if (strlen(json_encode($validated['payload'])) > self::MAX_PAYLOAD_BYTES) {
            abort(422, 'Configuration trop volumineuse.');
        }

        return $validated;
    }
}
