<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str; // Add this line
use App\Models\ChatHistory;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);


        $user = $request->user();

        // Save the user's message in the chat_histories table
        $session_id = (string) Str::uuid(); // Generate a new session ID if needed

        ChatHistory::create([
            'user_id' => $user->id,
            'session_id' => $session_id,
            'user_message' => $request->message,
            'bot_response' => '',  
        ]);


        $response = Http::post('http://localhost:11434/api/generate', [
            'model' => 'mistral',
            'prompt' => $request->message,
            'stream' => false,
        ]);

        // Get the bot's response
        $bot_response = data_get($response->json(), 'response', 'No response from LLM');


        ChatHistory::where('user_id', $user->id)
            ->where('session_id', $session_id)
            ->update(['bot_response' => $bot_response]);

        // Return the LLM's response
        return response()->json([
            'message' => $bot_response,
            'session_id' => $session_id,
        ]);
    }
}
