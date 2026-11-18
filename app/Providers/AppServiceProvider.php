<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

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
       Limit::perMinute(5)->by($r->input('email').'|'.$r->ip())
    );
       RateLimiter::for('auth-register', fn (Request $r) =>
       Limit::perMinute(3)->by($r->ip())
    );

    try {
        $databaseName = env('DB_DATABASE', 'loan_management');

        // Temporarily connect without a database name to check/create it
        $config = Config::get('database.connections.mysql');
        $config['database'] = null; 
        
        $pdo = new \PDO(
            "mysql:host={$config['host']};port={$config['port']}", 
            $config['username'], 
            $config['password'], 
            $config['options'] ?? []
        );

        // Create the database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        
    } catch (\Exception $e) {
        // Log or handle connection exception during build if needed
    }
}
}