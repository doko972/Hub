<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class SharedConversationController extends Controller
{
    public function show(string $token)
    {
        $conversation = Conversation::where('share_token', $token)->firstOrFail();
        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();

        return view('shared.conversation', compact('conversation', 'messages'));
    }
}
