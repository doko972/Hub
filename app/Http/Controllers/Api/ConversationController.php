<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * Liste des conversations de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->conversations()
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($conversations);
    }

    /**
     * Créer une nouvelle conversation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
        ]);

        $conversation = $request->user()->conversations()->create([
            'title' => $validated['title'] ?? 'Nouvelle conversation',
        ]);

        return response()->json($conversation, 201);
    }

    /**
     * Afficher une conversation avec ses messages
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $conversation->load('messages');

        return response()->json($conversation);
    }

    /**
     * Modifier le titre d'une conversation
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $conversation->update($validated);

        return response()->json($conversation);
    }

    /**
     * Supprimer une conversation
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $conversation->delete();

        return response()->json(['message' => 'Conversation supprimée']);
    }
    /**
     * Déplacer une conversation dans un dossier
     */
    public function move(Request $request, Conversation $conversation): JsonResponse
    {
        // Vérifier que la conversation appartient à l'utilisateur
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        // Si folder_id est fourni, vérifier que le dossier appartient à l'utilisateur
        if (!empty($validated['folder_id'])) {
            $folder = \App\Models\Folder::find($validated['folder_id']);
            if ($folder->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }
        }

        $conversation->update([
            'folder_id' => $validated['folder_id'] ?? null,
        ]);

        return response()->json($conversation);
    }
}