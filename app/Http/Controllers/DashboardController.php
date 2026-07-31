<?php

namespace App\Http\Controllers;

use App\Models\ToolFamily;
use App\Models\UserToolCredential;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $families = ToolFamily::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['tools' => function ($q) use ($user) {
                $q->where('is_active', true)->orderBy('sort_order')->orderBy('title');
                if (!$user->isAdmin()) {
                    // Si l'utilisateur a des outils assignés → uniquement les siens
                    // Sinon → outils publics (repli)
                    if ($user->tools()->exists()) {
                        $q->whereHas('users', fn($q2) => $q2->where('users.id', $user->id));
                    } else {
                        $q->where('is_public', true);
                    }
                    // Filtre de sélection personnelle (si l'utilisateur a des préférences)
                    $selectedIds = $user->selectedTools()->allRelatedIds();
                    if ($selectedIds->isNotEmpty()) {
                        $q->whereIn('id', $selectedIds);
                    }
                }
            }])
            ->get()
            ->filter(fn($f) => $f->tools->isNotEmpty());

        // Identifiants des outils : on n'expose QUE la liste des outils qui en
        // possèdent. Les valeurs (déchiffrées) sont récupérées à la demande via
        // credentials.show, pour ne pas semer tout le coffre dans le HTML.
        $toolsWithCredentials = UserToolCredential::where('user_id', $user->id)
            ->pluck('tool_id')
            ->all();

        return view('dashboard.index', compact('families', 'toolsWithCredentials'));
    }
}
