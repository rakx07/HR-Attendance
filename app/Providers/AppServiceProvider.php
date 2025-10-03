<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Log queries only in local/dev environment
        if ($this->app->environment('local')) {
            DB::listen(function ($query) {
                // Log queries that take more than 50ms
                if ($query->time > 50) {
                    Log::info("[SQL {$query->time}ms] {$query->sql}", $query->bindings);
                }
            });
        }
    }
}
