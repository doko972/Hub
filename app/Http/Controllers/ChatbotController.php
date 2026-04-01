<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
    /**
     * Retourne l'URL du chat intégré.
     * Le chatbot est désormais dans le même projet — la session est déjà partagée.
     */
    public function getUrl(): JsonResponse
    {
        return response()->json([
            'url' => route('cortex.chat'),
        ]);
    }
}
