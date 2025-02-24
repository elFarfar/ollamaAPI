<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\ChatHistory;
use Ramsey\Uuid\Uuid;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        // Validate input
        $request->validate([
            'message' => 'required|string',
            'session_id' => 'nullable|string'
        ]);

        $user = Auth::user(); // Get authenticated user (if any)
        $session_id = $request->session_id;

        if ($user && !$session_id) {
            // If the user is authenticated but no session_id exists, generate a new one
            $session_id = (string) Uuid::uuid4();
        }

        // Fetch previous messages for session (if session_id exists)
        $previousMessages = [];
        if ($user && $session_id) {
            $previousMessages = ChatHistory::where('user_id', $user->id)
                ->where('session_id', $session_id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn($chat) => [
                    ['role' => 'user', 'content' => $chat->user_message],
                    ['role' => 'assistant', 'content' => $chat->bot_response],
                ])
                ->flatten(1)
                ->toArray();
        }

        // Add current message
        $messages = array_merge($previousMessages, [
            ['role' => 'user', 'content' => $request->message]
        ]);

        // Call the LLM API
        $response = Http::post('http://localhost:11434/api/chat', [
            'model' => 'mistral',
            'messages' => $messages,
            'stream' => false,
        ]);

        $data = $response->json();
        $botResponse = $data['message'] ?? 'No response from AI';

        // Save message history
        if ($user) {
            ChatHistory::create([
                'user_id' => $user->id,
                'session_id' => $session_id,
                'user_message' => $request->message,
                'bot_response' => $botResponse,
            ]);
        }

        return response()->json([
            'session_id' => $session_id,
            'response' => $botResponse,
        ]);
    }
}
