<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.mcp.api_token');

        if (! is_string($expectedToken) || $expectedToken === '') {
            return ApiResponse::error('La API MCP no está configurada.', null, 503);
        }

        $providedToken = $request->bearerToken();

        if (! is_string($providedToken) || ! hash_equals($expectedToken, $providedToken)) {
            return ApiResponse::error('No autenticado.', null, 401);
        }

        return $next($request);
    }
}