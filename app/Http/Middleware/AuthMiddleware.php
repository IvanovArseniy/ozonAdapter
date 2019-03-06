<?php

namespace App\Http\Middleware;

use Closure;

class AuthMiddleware
{
    protected $authToken;

    public function __construct()
    {
        $this->authToken = 'token_b569150ba79f4d1b81c00443d';
    }

    public function handle($request, Closure $next)
    {
        if ($request->input('token') && $request->input('token') == $this->authToken) {
            return $next($request);
        }
        return response('Unauthorized', 401);
    }
}