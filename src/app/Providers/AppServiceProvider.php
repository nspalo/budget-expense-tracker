<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 60;

                    return response()->json([
                        'message' => 'Too many requests. Please try again later.',
                        'retry_after' => (int) $retryAfter,
                    ], 429)->withHeaders([
                        'Retry-After' => $retryAfter,
                    ]);
                });
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $email = strtolower($request->input('email', ''));

            return Limit::perMinute(5)
                ->by($email.'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 60;

                    return response()->json([
                        'message' => 'Too many requests. Please try again later.',
                        'retry_after' => (int) $retryAfter,
                    ], 429)->withHeaders([
                        'Retry-After' => $retryAfter,
                    ]);
                });
        });
    }
}
