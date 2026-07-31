<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ImageQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class ImageController extends Controller
{
    /**
     * Générer une image avec DALL-E 3
     */
    public function generate(Request $request, Conversation $conversation): JsonResponse
    {
        // Vérifier que la conversation appartient à l'utilisateur
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Vérifier la limite quotidienne (les deux chemins de génération)
        $todayCount = ImageQuota::usedToday($request->user()->id);

        if ($todayCount >= ImageQuota::DAILY_LIMIT) {
            $limit = ImageQuota::DAILY_LIMIT;

            return response()->json([
                'message' => "Limite atteinte ({$limit} images/jour). Réessayez demain !",
                'limit_reached' => true,
                'count' => $todayCount,
                'limit' => $limit,
            ], 429);
        }

        $validated = $request->validate([
            'prompt' => 'required|string|max:4000',
            'size' => 'nullable|string|in:1024x1024,1792x1024,1024x1792',
            'quality' => 'nullable|string|in:standard,hd',
        ]);

        $prompt = $validated['prompt'];
        $size = $validated['size'] ?? '1024x1024';
        $quality = $validated['quality'] ?? 'standard';

        // Sauvegarder le message utilisateur
        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => '/imagine ' . $prompt,
        ]);

        try {
            // Appel à DALL-E 3
            $response = OpenAI::images()->create([
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'quality' => $quality,
            ]);

            $imageUrl = $response->data[0]->url;
            $revisedPrompt = $response->data[0]->revisedPrompt ?? $prompt;

            // Calculer les images restantes
            $limit     = ImageQuota::DAILY_LIMIT;
            $remaining = max(0, $limit - $todayCount - 1);

            // Sauvegarder la réponse avec l'URL de l'image
            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => "🎨 **Image générée**\n\n![Image générée]({$imageUrl})\n\n*Prompt : {$revisedPrompt}*\n\n📊 *Images restantes aujourd'hui : {$remaining}/{$limit}*",
            ]);

            $conversation->touch();

            return response()->json([
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
                'image_url' => $imageUrl,
                'revised_prompt' => $revisedPrompt,
                'remaining' => $remaining,
                'limit' => self::DAILY_LIMIT,
            ]);
        } catch (\Exception $e) {
            Log::error('Génération DALL-E échouée', ['exception' => $e]);

            return response()->json([
                'message' => 'Erreur lors de la génération de l\'image',
            ], 500);
        }
    }

    /**
     * Domaines autorisés pour le proxy d'images (OpenAI uniquement)
     */
    private const ALLOWED_HOSTS = [
        'oaidalleapiprodscus.blob.core.windows.net',
        'dalleprodsec.blob.core.windows.net',
    ];

    public function proxy(Request $request)
    {
        $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $url = $request->url;
        $host = parse_url($url, PHP_URL_HOST);

        // Validation SSRF : seuls les domaines OpenAI sont autorisés
        if (!in_array($host, self::ALLOWED_HOSTS)) {
            return response()->json(['message' => 'URL non autorisée.'], 403);
        }

        try {
            $response = Http::timeout(15)->get($url);

            if (!$response->successful()) {
                return response()->json(['message' => 'Impossible de télécharger l\'image'], 400);
            }

            $fileName = 'generated_' . time() . '_' . uniqid() . '.png';
            $path = 'generated/' . $fileName;
            Storage::disk('public')->put($path, $response->body());

            return response()->json([
                'success' => true,
                'image_url' => asset('storage/' . $path),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors du proxy d\'image.'], 500);
        }
    }
}
