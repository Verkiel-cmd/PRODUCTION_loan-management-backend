<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

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
       RateLimiter::for('auth-login', fn (Request $r) =>
       Limit::perMinute(5)->by($r->input('email').'|'.$r->ip()));

       RateLimiter::for('auth-register', fn (Request $r) =>
       Limit::perMinute(3)->by($r->ip()));
    }
}
