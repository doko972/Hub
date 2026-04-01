<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use Carbon\Carbon;

class ImageController extends Controller
{
    /**
     * Limite d'images par jour par utilisateur
     */
    private const DAILY_LIMIT = 20;

    /**
     * Générer une image avec DALL-E 3
     */
    public function generate(Request $request, Conversation $conversation): JsonResponse
    {
        // Vérifier que la conversation appartient à l'utilisateur
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Vérifier la limite quotidienne
        $todayCount = $this->getTodayImageCount($request->user()->id);

        if ($todayCount >= self::DAILY_LIMIT) {
            return response()->json([
                'message' => "Limite atteinte ({self::DAILY_LIMIT} images/jour). Réessayez demain !",
                'limit_reached' => true,
                'count' => $todayCount,
                'limit' => self::DAILY_LIMIT,
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
            $remaining = self::DAILY_LIMIT - $todayCount - 1;

            // Sauvegarder la réponse avec l'URL de l'image
            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => "🎨 **Image générée**\n\n![Image générée]({$imageUrl})\n\n*Prompt : {$revisedPrompt}*\n\n📊 *Images restantes aujourd'hui : {$remaining}/{self::DAILY_LIMIT}*",
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
            return response()->json([
                'message' => 'Erreur lors de la génération de l\'image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compter les images générées aujourd'hui par l'utilisateur
     */
    private function getTodayImageCount(int $userId): int
    {
        return \App\Models\Message::whereHas('conversation', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->where('role', 'user')
            ->where('content', 'like', '/imagine %')
            ->whereDate('created_at', Carbon::today())
            ->count();
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
