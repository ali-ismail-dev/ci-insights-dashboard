<?php

use App\Http\Controllers\Api\{
    AlertController,
    DashboardController,
    PullRequestController,
    RepositoryController,
    TestRunController,
    WebhookController
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Sanctum CSRF cookie route (required for SPA authentication)
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
})->middleware('web');

// Authentication routes (token-based, no sessions needed)
Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (Auth::attempt($request->only('email', 'password'))) {
        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;
        return response()->json([
            'message' => 'Logged in successfully',
            'user' => $user,
            'token' => $token
        ]);
    }

    return response()->json(['message' => 'Invalid credentials'], 401);
});

Route::post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Logged out successfully']);
})->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Webhooks (no auth)
Route::prefix('webhooks')->group(function () {
    Route::post('github', [WebhookController::class, 'github'])->name('webhooks.github');
    Route::post('test', [WebhookController::class, 'test'])->name('webhooks.test');
});

// Public routes
Route::get('health', fn() => response()->json(['status' => 'healthy']));

// Protected API routes
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Dashboard
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    
    // Repositories
    Route::apiResource('repositories', RepositoryController::class)->only(['index', 'show']);
    Route::get('repositories/{repository}/pull-requests', [PullRequestController::class, 'index']);
    Route::get('repositories/{repository}/test-runs', [TestRunController::class, 'index']);
    Route::get('repositories/{repository}/flaky-tests', [TestRunController::class, 'flakyTests']);
    
    // Pull Requests
    Route::apiResource('pull-requests', PullRequestController::class)->only(['show']);
    
    // Test Runs
    Route::apiResource('test-runs', TestRunController::class)->only(['show']);
    
    // Alerts
    Route::apiResource('alerts', AlertController::class)->only(['index']);
    Route::post('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge']);
    Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve']);
});