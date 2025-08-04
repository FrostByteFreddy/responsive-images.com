<?php 

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {

        // Ddefault API rate limiter from laravel
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter for /v1/generate endpoint
        RateLimiter::for('generator', function (Request $request) {

            // 3 max attempts per 1440 minutes (24 hours), identified by IP address.
            return Limit::perMinutes(1440, 3)->by($request->ip());
        });
    }
}