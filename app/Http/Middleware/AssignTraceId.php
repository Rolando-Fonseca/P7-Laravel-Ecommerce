<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TraceId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corre el primero de la cadena.
 *
 * Sin esto, "me fallo el pedido ayer por la tarde" no se puede investigar. Con esto,
 * el cliente pega su traceId y aparece la peticion completa en el log.
 */
class AssignTraceId
{
    public function handle(Request $request, Closure $next): Response
    {
        // Se acepta un traceId propuesto por el cliente solo si tiene forma de ULID.
        // Asi una cadena de servicios puede propagar el mismo identificador.
        $incoming = (string) $request->header('X-Trace-Id', '');
        $traceId = preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $incoming) === 1
            ? $incoming
            : (string) Str::ulid();

        Context::add(TraceId::KEY, $traceId);

        $response = $next($request);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
