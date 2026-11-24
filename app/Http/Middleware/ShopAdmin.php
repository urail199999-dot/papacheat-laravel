<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ShopAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->is_admin && $request->user()->name === 'urail1999', 403);

        return $next($request);
    }
}
