<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UpdateController extends Controller
{
    /**
     * Vérifie si une mise à jour est disponible
     * GET /api/update/check?version=1.0.0&platform=windows
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'version' => 'required|string',
            'platform' => 'required|string|in:windows,macos,linux',
        ]);

        $currentVersion = $request->input('version');
        $platform = $request->input('platform');

        $latestRelease = AppRelease::getLatestForPlatform($platform);

        if (!$latestRelease) {
            return response()->json([
                'update_available' => false,
                'message' => 'Aucune version disponible',
            ]);
        }

        if ($latestRelease->isNewerThan($currentVersion)) {
            return response()->json([
                'update_available' => true,
                'version' => $latestRelease->version,
                'changelog' => $latestRelease->changelog,
                'file_size' => $latestRelease->file_size,
                'is_mandatory' => $latestRelease->is_mandatory,
                'download_url' => url("/api/update/download/{$latestRelease->id}"),
            ]);
        }

        return response()->json([
            'update_available' => false,
            'message' => 'Vous avez la dernière version',
        ]);
    }

    /**
     * Télécharge le fichier de mise à jour
     * GET /api/update/download/{id}
     */
    public function download(AppRelease $release): BinaryFileResponse|JsonResponse
    {
        if (!$release->is_active) {
            return response()->json([
                'error' => 'Cette version n\'est plus disponible',
            ], 404);
        }

        $filePath = storage_path("app/{$release->file_path}");

        if (!file_exists($filePath)) {
            return response()->json([
                'error' => 'Fichier introuvable',
            ], 404);
        }

        return response()->download($filePath, $release->file_name);
    }
}