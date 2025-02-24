<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\ChatHistory;

class ChatHistoryController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        
        $user = $request->user();

        
        $session_id = $request->session_id ?? (string) Str::uuid();
        if (!Str::isUuid($session_id)) $session_id = (string) Str::uuid();

        
        $previousMessages = ChatHistory::where('user_id', $user->id ?? null)
            ->where('session_id', $session_id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($chat) => [
                ['role' => 'user', 'content' => $chat->user_message],
                ['role' => 'assistant', 'content' => $chat->bot_response],
            ])
            ->flatten(1)
            ->toArray();

        
        $messages = array_merge($previousMessages, [
            ['role' => 'user', 'content' => $request->message]
        ]);
        set_time_limit(0);
        

        
        $response = Http::timeout(120)->post('http://localhost:11434/api/chat', [
            'model' => 'mistral',
            'messages' => $messages,
            'stream' => false
        ]);

        $data = $response->json();
        
        $botResponse = $data['message']['content'] ?? 'No response from AI';

        
        ChatHistory::create([
            'user_id' => $user->id ?? null,
            'session_id' => $session_id,
            'user_message' => $request->message,
            'bot_response' => $botResponse,
        ]);

        return response()->json([
            'session_id' => $session_id,
            'message' => $botResponse
        ]);
    }
}