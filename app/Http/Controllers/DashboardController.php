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
                    // Filtre de visibilité (public + assigné)
                    $q->where(function ($q2) use ($user) {
                        $q2->where('is_public', true)
                           ->orWhereHas('users', fn($q3) => $q3->where('users.id', $user->id));
                    });
                    // Filtre de sélection personnelle (si l'utilisateur a des préférences)
                    if ($user->selectedTools()->exists()) {
                        $selectedIds = $user->selectedTools()->pluck('tools.id');
                        $q->whereIn('tools.id', $selectedIds);
                    }
                }
            }])
            ->get()
            ->filter(fn($f) => $f->tools->isNotEmpty());

        // Credentials de l'utilisateur, indexés par tool_id
        $credentials = UserToolCredential::where('user_id', $user->id)
            ->get()
            ->keyBy('tool_id')
            ->map(fn($c) => [
                'login'    => $c->login,
                'password' => $c->password, // auto-déchiffré par le cast 'encrypted'
            ]);

        return view('dashboard.index', compact('families', 'credentials'));
    }
}
