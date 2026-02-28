<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index()
    {
        // Seuls les outils non-publics peuvent être assignés manuellement
        $tools = Tool::where('is_active', true)
            ->where('is_public', false)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $users = User::where('role', 'user')
            ->where('is_active', true)
            ->orderBy('name')
            ->withCount('tools')
            ->get();

        return view('admin.assignments.index', compact('tools', 'users'));
    }

    public function update(Request $request)
    {
        $toolIds = $request->input('tools', []);
        $userIds = $request->input('users', []);
        $action  = $request->input('action', 'assign');

        if (empty($toolIds) || empty($userIds)) {
            return back()->with('error', 'Sélectionnez au moins un outil et un utilisateur.');
        }

        $users = User::whereIn('id', $userIds)->get();
        $count = count($toolIds);

        foreach ($users as $user) {
            if ($action === 'assign') {
                $user->tools()->syncWithoutDetaching($toolIds);
            } else {
                $user->tools()->detach($toolIds);
            }
        }

        $verb = $action === 'assign' ? 'assigné(s) à' : 'retiré(s) de';
        $msg  = "{$count} outil(s) {$verb} " . count($userIds) . " utilisateur(s).";
        ActivityLog::record('assigned', 'Assignation', null, $msg);

        return back()->with('success', $msg);
    }
}
