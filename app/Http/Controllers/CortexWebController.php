<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class CortexWebController extends Controller
{
    /**
     * Affiche l'interface de chat Cortex Web
     */
    public function index(Request $request)
    {
        if (!session('api_token')) {
            $user = auth()->user();
            $deviceId = substr(md5($request->userAgent() . $request->ip()), 0, 8);
            $tokenName = 'cortex-web-' . $deviceId;

            $user->tokens()->where('name', $tokenName)->delete();
            $token = $user->createToken($tokenName)->plainTextToken;
            session(['api_token' => $token]);
        }

        return view('cortex.chat');
    }

    /**
     * Affiche une conversation spécifique
     */
    public function show(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        if (!session('api_token')) {
            $user = auth()->user();
            $deviceId = substr(md5($request->userAgent() . $request->ip()), 0, 8);
            $tokenName = 'cortex-web-' . $deviceId;

            $user->tokens()->where('name', $tokenName)->delete();
            $token = $user->createToken($tokenName)->plainTextToken;
            session(['api_token' => $token]);
        }

        return view('cortex.chat', [
            'currentConversation' => $conversation
        ]);
    }
}
