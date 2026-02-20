<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit; // Add this
use Illuminate\Http\Request;             // Add this
use Illuminate\Support\Facades\RateLimiter; // Add this

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        
        RateLimiter::for('webhooks', function (Request $request) {
        return Limit::perMinute(60)->by($request->ip());
    });
    }
}
