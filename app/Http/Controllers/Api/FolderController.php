<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    /**
     * Liste des dossiers de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $folders = $request->user()
            ->folders()
            ->with('conversations')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return response()->json($folders);
    }

    /**
     * Créer un nouveau dossier
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $folder = $request->user()->folders()->create([
            'name' => $validated['name'],
            'position' => $request->user()->folders()->count(),
        ]);

        return response()->json($folder, 201);
    }

    /**
     * Renommer un dossier
     */
    public function update(Request $request, Folder $folder): JsonResponse
    {
        // Vérifier que le dossier appartient à l'utilisateur
        if ($folder->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $folder->update($validated);

        return response()->json($folder);
    }

    /**
     * Supprimer un dossier (les conversations reviennent à la racine)
     */
    public function destroy(Request $request, Folder $folder): JsonResponse
    {
        // Vérifier que le dossier appartient à l'utilisateur
        if ($folder->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Remettre les conversations à la racine (folder_id = null)
        $folder->conversations()->update(['folder_id' => null]);

        // Supprimer le dossier
        $folder->delete();

        return response()->json(['message' => 'Dossier supprimé']);
    }
}