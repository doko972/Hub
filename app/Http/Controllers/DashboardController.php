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

                // ---- 1. Droits d'accès : ce que l'utilisateur a le droit de voir ----
                // Un administrateur voit tout ; les autres, leurs outils assignés,
                // ou les outils publics à défaut d'assignation.
                if (!$user->isAdmin()) {
                    if ($user->tools()->exists()) {
                        $q->whereHas('users', fn($q2) => $q2->where('users.id', $user->id));
                    } else {
                        $q->where('is_public', true);
                    }
                }

                // ---- 2. Préférence d'affichage : ce qu'il souhaite voir ----
                // S'applique à tout le monde, administrateurs compris : choisir
                // ses vignettes relève du confort, pas des droits. Ce filtre
                // était auparavant imbriqué dans le test ci-dessus, si bien que
                // la sélection d'un administrateur restait sans effet.
                //
                // Une sélection vide signifie « aucune préférence » : on affiche
                // alors tout ce qui est accessible.
                $selectedIds = $user->selectedTools()->allRelatedIds();
                if ($selectedIds->isNotEmpty()) {
                    $q->whereIn('id', $selectedIds);
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
