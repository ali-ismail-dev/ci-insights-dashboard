<?php

declare(strict_types=1);

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Webhooks
|--------------------------------------------------------------------------
|
| Webhook routes for receiving GitHub/GitLab events.
| 
| Rate limiting: 30 requests/minute per IP (configured in Nginx)
| Additional application-level throttling applied via middleware.
|
*/

// Webhook endpoints (no authentication - verified via signature)
Route::prefix('webhooks')->group(function () {
    
    // GitHub webhook endpoint
    // POST /api/webhooks/github
    Route::post('github', [WebhookController::class, 'github'])
        ->middleware('throttle:webhooks') // 60 req/min
        ->name('webhooks.github');
    
    // GitLab webhook endpoint (future)
    // POST /api/webhooks/gitlab
    Route::post('gitlab', [WebhookController::class, 'gitlab'])
        ->middleware('throttle:webhooks')
        ->name('webhooks.gitlab');
    
    // Test webhook endpoint (dev/staging only)
    // POST /api/webhooks/test
    Route::post('test', [WebhookController::class, 'test'])
        ->middleware('throttle:60,1') // More lenient for testing
        ->name('webhooks.test');
});

/*
|--------------------------------------------------------------------------
| Throttle Configuration
|--------------------------------------------------------------------------
|
| Define custom throttle limits in app/Providers/RouteServiceProvider.php:
|
| RateLimiter::for('webhooks', function (Request $request) {
|     return Limit::perMinute(60)->by($request->ip());
| });
|
*/