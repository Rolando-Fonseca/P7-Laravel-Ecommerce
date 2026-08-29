<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\AssignTraceId;
use App\Http\Middleware\EnforceIdempotency;
use App\Support\ApiError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // AssignTraceId va el primero: toda respuesta, con exito o con fallo, debe
        // llevar X-Trace-Id.
        $middleware->api(prepend: [AssignTraceId::class]);

        $middleware->alias([
            'idempotency' => EnforceIdempotency::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Un unico punto de traduccion de excepciones al formato de error de
        // docs/api/contracts/00-convenciones.md.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof DomainException) {
                return ApiError::response($e->code(), $e->getMessage(), $e->details(), $e->httpStatus());
            }

            if ($e instanceof ValidationException) {
                $details = [];
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $details[] = ['field' => $field, 'issue' => $message];
                    }
                }

                return ApiError::response(
                    'VALIDATION_FAILED',
                    'Los datos enviados no son validos.',
                    $details,
                    422
                );
            }

            if ($e instanceof AuthenticationException) {
                return ApiError::response('UNAUTHENTICATED', 'Se requiere autenticacion.', [], 401);
            }

            if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
                return ApiError::response('FORBIDDEN', 'No tienes permiso para esta operacion.', [], 403);
            }

            if ($e instanceof ThrottleRequestsException) {
                return ApiError::response('RATE_LIMITED', 'Demasiadas peticiones. Reintenta en unos segundos.', [], 429);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return ApiError::response('RESOURCE_NOT_FOUND', 'El recurso solicitado no existe.', [], 404);
            }

            // Fallo no controlado: el cliente recibe el traceId y nada mas. El stack
            // trace va al log, indexado por ese mismo traceId.
            if (! config('app.debug')) {
                return ApiError::response(
                    'INTERNAL_ERROR',
                    'Ocurrio un error inesperado. Reporta el traceId para investigarlo.',
                    [],
                    500
                );
            }

            return null;
        });
    })->create();
