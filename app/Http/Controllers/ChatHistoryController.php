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
        // Ensure the user is authenticated
        $user = $request->user();

        // Validate the incoming message
        $request->validate([
            'message' => 'required|string',
        ]);

        // Generate or use session_id from the request
        $session_id = $request->session_id ?? (string) Str::uuid();
        if (!Str::isUuid($session_id)) {
            $session_id = (string) Str::uuid();
        }

        // Fetch previous messages from the database if the user is authenticated
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

        // Append the current user message to the conversation history
        $messages = array_merge($previousMessages, [
            ['role' => 'user', 'content' => $request->message]
        ]);

        // Set the time limit for the LLM API request
        set_time_limit(0);

        // Send the conversation to the LLM for response
        $response = Http::timeout(120)->post('http://localhost:11434/api/chat', [
            'model' => 'mistral',
            'messages' => $messages,
            'stream' => false
        ]);

        // Get the response content from the LLM
        $data = $response->json();

        // Check for errors in the LLM response
        $botResponse = $data['message']['content'] ?? 'No response from AI';

        // Store the chat history in the database
        ChatHistory::create([
            'user_id' => $user->id,
            'session_id' => $session_id,
            'user_message' => $request->message,
            'bot_response' => $botResponse,
        ]);

        // Return the response to the client
        return response()->json([
            'session_id' => $session_id,
            'message' => $botResponse,
        ]);
    }
}
