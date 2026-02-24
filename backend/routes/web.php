<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('welcome');
});

// Sanctum Authentication Routes (no CSRF for SPA)
Route::withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::post('/login', function (Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $request->session()->regenerate();
            return response()->json(['message' => 'Logged in successfully']);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    });

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Logged out successfully']);
    })->middleware('auth:sanctum');

    Route::get('/api/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');
});
