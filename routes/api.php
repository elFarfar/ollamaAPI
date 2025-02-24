<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Define only ONE chat route (choose the correct controller)
Route::post('/chat', [ChatbotController::class, 'chat'])->middleware('auth:sanctum');

Route::post('/test', function (Request $request) {
    $data = $request->test;

    dump($data);

    return response()->json(['Message' => "Incoming data was: $data"]);
});

Route::get('/getRouteTest', function (Request $request) {
    try {
        $request->validate([
            'test' => 'required|string',
        ]);

        return response()->json(['Message' => "Hello from API"]);
    } catch (\Exception $e) {
        return response()->json(['message' => "Internal Server Error"], 500);
    }
});

