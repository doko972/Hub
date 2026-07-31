<?php

namespace App\Http\Controllers;

use App\Models\ToolFamily;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferencesController extends Controller
{
    /**
     * Enregistre le thème choisi.
     *
     * Répond en JSON quand l'appel vient du JS (bascule immédiate depuis la
     * navbar, sans rechargement), et redirige sinon pour rester utilisable
     * si JavaScript est indisponible.
     */
    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::in(array_keys(config('themes.available')))],
        ]);

        $request->user()->update(['theme' => $validated['theme']]);

        if ($request->expectsJson()) {
            return response()->json(['theme' => $validated['theme']]);
        }

        return back()->with('success', 'Thème mis à jour.');
    }

    /**
     * Page de choix du thème.
     */
    public function theme()
    {
        return view('preferences.theme', [
            'themes'  => config('themes.available'),
            'current' => auth()->user()->effectiveTheme(),
        ]);
    }

    public function edit()
    {
        $user = auth()->user();

        // Tous les outils accessibles à cet utilisateur, groupés par famille
        $families = ToolFamily::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['tools' => function ($q) use ($user) {
                $q->where('is_active', true)->orderBy('sort_order')->orderBy('title');
                if (!$user->isAdmin()) {
                    if ($user->tools()->exists()) {
                        $q->whereHas('users', fn($q2) => $q2->where('users.id', $user->id));
                    } else {
                        $q->where('is_public', true);
                    }
                }
            }])
            ->get()
            ->filter(fn($f) => $f->tools->isNotEmpty());

        $selectedIds = $user->selectedTools()->allRelatedIds()->toArray();

        return view('preferences.edit', compact('families', 'selectedIds'));
    }

    public function update(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'tools'   => ['nullable', 'array'],
            'tools.*' => ['exists:tools,id'],
        ]);

        auth()->user()->selectedTools()->sync($request->input('tools', []));

        return redirect()->route('preferences.edit')
            ->with('success', 'Vos préférences ont été enregistrées.');
    }
}
