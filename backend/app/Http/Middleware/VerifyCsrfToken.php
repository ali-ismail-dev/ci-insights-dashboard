<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;


class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '*', // Exclude all routes from CSRF
    ];

    /**
     * Determine if the request should skip CSRF verification.
     */
    protected function shouldSkip($request)
    {
        return true; // Skip CSRF for all requests
    }
}
