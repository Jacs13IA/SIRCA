<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Support\ApiResponse;
use App\Http\Middleware\AuthenticateMcpApi;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'mcp.api' => AuthenticateMcpApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return ApiResponse::error('La solicitud contiene datos inválidos.', ['errors' => $exception->errors()], 400);
            }

            if ($exception instanceof AuthenticationException) {
                return ApiResponse::error('No autenticado.', null, 401);
            }

            if ($exception instanceof AuthorizationException) {
                return ApiResponse::error('No autorizado.', null, 403);
            }

            if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
                return ApiResponse::error('Recurso no encontrado.', null, 404);
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $message = $status === 429 ? 'Demasiadas solicitudes. Intenta más tarde.' : 'No fue posible procesar la solicitud.';

                return ApiResponse::error($message, null, $status);
            }

            report($exception);

            return ApiResponse::error('Error interno del servidor.', null, 500);
        });
    })->create();
