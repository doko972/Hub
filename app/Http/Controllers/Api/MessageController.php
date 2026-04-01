<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Liste des messages d'une conversation
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();

        return response()->json($messages);
    }

    /**
     * Ajouter un message à une conversation
     */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'role' => 'required|in:user,assistant',
            'content' => 'required|string',
        ]);

        $message = $conversation->messages()->create($validated);

        $conversation->touch();

        return response()->json($message, 201);
    }

    /**
     * Afficher un message spécifique
     */
    public function show(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($message->conversation_id !== $conversation->id) {
            return response()->json(['message' => 'Message non trouvé'], 404);
        }

        return response()->json($message);
    }

    /**
     * Supprimer un message
     */
    public function destroy(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($message->conversation_id !== $conversation->id) {
            return response()->json(['message' => 'Message non trouvé'], 404);
        }

        $message->delete();

        return response()->json(['message' => 'Message supprimé']);
    }
}