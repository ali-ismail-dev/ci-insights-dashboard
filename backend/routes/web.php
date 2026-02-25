<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;

Route::get('/', function () {
    return view('welcome');
});

// --- Public Auth Routes (Handled by Web Middleware for Sessions) ---

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();
        return response()->json(['message' => 'Logged in successfully']);
    }

    return response()->json(['message' => 'Invalid credentials'], 401);
});

// GitHub Handshake
Route::get('/auth/github/redirect', function () {
    return Socialite::driver('github')->redirect();
})->name('github.login');

Route::get('/auth/github/callback', function () {
    try {
        $githubUser = Socialite::driver('github')->user();
        
        $user = User::updateOrCreate([
            'external_id' => $githubUser->id,
            'provider' => 'github',
        ], [
            'name' => $githubUser->getName() ?? $githubUser->getNickname(),
            'email' => $githubUser->getEmail(),
            'username' => $githubUser->getNickname(),
            'avatar_url' => $githubUser->getAvatar(),
            'password' => bcrypt(str()->random(24)),
            'is_active' => true,
            'role' => 'user',
            'timezone' => 'UTC',
            'email_notifications' => true,
            'slack_notifications' => false,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;
        return redirect('http://localhost:3000/?token=' . $token);
        
    } catch (\Exception $e) {
        return redirect('http://localhost:3000/login?error=github_failed');
    }
});

// --- Protected Routes (Require Session/Sanctum) ---

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Logged out successfully']);
    });

    Route::get('/api/user', function (Request $request) {
        return $request->user();
    });
});
