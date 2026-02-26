<?php

namespace App\Http\Controllers;

use App\Models\ToolFamily;

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
                    $q->where(function ($q2) use ($user) {
                        $q2->where('is_public', true)
                           ->orWhereHas('users', fn($q3) => $q3->where('users.id', $user->id));
                    });
                }
            }])
            ->get()
            ->filter(fn($f) => $f->tools->isNotEmpty());

        return view('dashboard.index', compact('families'));
    }
}
