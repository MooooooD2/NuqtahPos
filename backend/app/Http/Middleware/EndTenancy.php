<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EndTenancy
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate($request, $response)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}
