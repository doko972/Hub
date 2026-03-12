<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\UserToolCredential;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
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
