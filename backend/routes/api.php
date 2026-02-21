<?php

use App\Http\Controllers\Api\{
    AlertController,
    DashboardController,
    PullRequestController,
    RepositoryController,
    TestRunController,
    WebhookController
};
use Illuminate\Support\Facades\Route;

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