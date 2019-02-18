<?php

namespace App\Http\Middleware;

use Closure;

class AuthMiddleware
{
    protected $authToken;

    public function __construct()
    {
        $this->authToken = 'test';
    }

    public function handle($request, Closure $next)
    {
        if ($request->header('Authorization') && $request->header('Authorization') == $this->authToken) {
            return $next($request);
        }
        return response('Unauthorized', 401);
    }
}