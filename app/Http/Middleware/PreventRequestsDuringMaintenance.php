<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * URIs that should be reachable even during maintenance mode.
     *
     * @var array<int, string>
     */
    protected $except = [
        // e.g. 'status/health'
    ];
}
