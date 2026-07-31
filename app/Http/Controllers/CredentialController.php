<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\UserToolCredential;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    /**
     * Renvoie les identifiants de l'utilisateur pour un outil.
     *
     * Ils ne sont plus injectés dans le HTML du tableau de bord : ils ne
     * quittent le serveur qu'à l'ouverture explicite de la modale, et la
     * réponse n'est jamais mise en cache.
     */
    public function show(Tool $tool)
    {
        $credential = UserToolCredential::where('user_id', auth()->id())
            ->where('tool_id', $tool->id)
            ->first();

        return response()
            ->json([
                'login'    => $credential->login ?? '',
                'password' => $credential->password ?? '',
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    public function store(Request $request, Tool $tool)
    {
        $request->validate([
            'login'    => 'nullable|string|max:255',
            'password' => 'nullable|string|max:1000',
        ]);

        UserToolCredential::updateOrCreate(
            ['user_id' => auth()->id(), 'tool_id' => $tool->id],
            ['login' => $request->login, 'password' => $request->password]
        );

        return response()->json(['success' => true]);
    }

    public function destroy(Tool $tool)
    {
        UserToolCredential::where('user_id', auth()->id())
            ->where('tool_id', $tool->id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
