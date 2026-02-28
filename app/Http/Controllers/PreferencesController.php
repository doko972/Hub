<?php

namespace App\Http\Controllers;

use App\Models\ToolFamily;

class PreferencesController extends Controller
{
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
                    $q->where(function ($q2) use ($user) {
                        $q2->where('is_public', true)
                           ->orWhereHas('users', fn($q3) => $q3->where('users.id', $user->id));
                    });
                }
            }])
            ->get()
            ->filter(fn($f) => $f->tools->isNotEmpty());

        $selectedIds       = $user->selectedTools()->pluck('tools.id')->toArray();
        $allCheckedByDefault = empty($selectedIds);

        return view('preferences.edit', compact('families', 'selectedIds', 'allCheckedByDefault'));
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
