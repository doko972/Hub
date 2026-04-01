<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SearchController extends Controller
{
    /**
     * Recherche sur le web via Brave Search API
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $query = $validated['query'];
        $apiKey = config('services.brave.api_key');

        if (!$apiKey) {
            return response()->json([
                'message' => 'Clé API Brave Search non configurée',
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'X-Subscription-Token' => $apiKey,
                'Accept' => 'application/json',
            ])->get('https://api.search.brave.com/res/v1/web/search', [
                'q' => $query,
                'count' => 5,
                'search_lang' => 'fr',
                'country' => 'fr',
                'text_decorations' => false,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Erreur lors de la recherche',
                    'error' => $response->body(),
                ], $response->status());
            }

            $data = $response->json();

            $results = [
                'query' => $query,
                'web_results' => [],
            ];

            if (!empty($data['web']['results'])) {
                foreach ($data['web']['results'] as $result) {
                    $results['web_results'][] = [
                        'title' => $result['title'] ?? '',
                        'description' => $result['description'] ?? '',
                        'url' => $result['url'] ?? '',
                    ];
                }
            }

            return response()->json($results);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}