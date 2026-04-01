<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemPrompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemPromptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $prompts = SystemPrompt::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json($prompts);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (SystemPrompt::where('user_id', $user->id)->count() >= 20) {
            return response()->json(['message' => 'Limite de 20 personnalités atteinte.'], 422);
        }

        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'content' => 'required|string|max:3000',
        ]);

        $prompt = SystemPrompt::create([
            'user_id' => $user->id,
            'name'    => $validated['name'],
            'content' => $validated['content'],
            'is_default' => false,
        ]);

        return response()->json($prompt, 201);
    }

    public function update(Request $request, SystemPrompt $systemPrompt): JsonResponse
    {
        if ($systemPrompt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'content' => 'required|string|max:3000',
        ]);

        $systemPrompt->update($validated);

        return response()->json($systemPrompt);
    }

    public function destroy(Request $request, SystemPrompt $systemPrompt): JsonResponse
    {
        if ($systemPrompt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $systemPrompt->delete();

        return response()->json(null, 204);
    }

    public function setDefault(Request $request, SystemPrompt $systemPrompt): JsonResponse
    {
        $user = $request->user();

        if ($systemPrompt->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Retirer le défaut de tous les autres
        SystemPrompt::where('user_id', $user->id)->update(['is_default' => false]);

        $systemPrompt->update(['is_default' => true]);

        return response()->json($systemPrompt);
    }
}
