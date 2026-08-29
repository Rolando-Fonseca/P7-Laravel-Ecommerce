<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyKeyRequiredException;
use App\Exceptions\IdempotencyKeyReusedException;
use App\Models\IdempotencyKey;
use App\Support\TraceId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADR-0004.
 *
 * Sin esto, un doble clic o un reintento por timeout de red crea dos pedidos y
 * reserva el stock dos veces.
 */
class EnforceIdempotency
{
    /** Ventana de retencion. Un reintento a las 25 horas si crearia un duplicado. */
    public const RETENTION_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');

        if ($key === '') {
            throw new IdempotencyKeyRequiredException;
        }

        $endpoint = $request->method().' '.$request->path();
        $hash = $this->hashBody($request);

        $record = IdempotencyKey::query()
            ->where('key', $key)
            ->where('endpoint', $endpoint)
            ->first();

        if ($record !== null) {
            // Misma clave con cuerpo distinto: es un bug del cliente y hay que
            // hacerlo ruidoso, no adivinar cual de las dos peticiones vale.
            if ($record->request_hash !== $hash) {
                throw new IdempotencyKeyReusedException($key);
            }

            if ($record->response_status !== null) {
                return response()
                    ->json($record->response_body, $record->response_status)
                    ->header('Idempotency-Replayed', 'true');
            }

            // Existe pero sin respuesta: hay otra peticion en curso con esta clave.
            return response()->json([
                'error' => [
                    'code' => 'IDEMPOTENCY_IN_PROGRESS',
                    'message' => 'Ya hay una peticion en curso con esta clave. Reintenta en unos segundos.',
                    'details' => [],
                    'traceId' => TraceId::current(),
                ],
            ], 409);
        }

        $record = IdempotencyKey::create([
            'key' => $key,
            'endpoint' => $endpoint,
            'request_hash' => $hash,
            'locked_at' => now(),
        ]);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Una excepcion de dominio sale por aqui, no por el return. Sin este
            // catch la fila quedaria bloqueada y el reintento legitimo del cliente
            // recibiria IDEMPOTENCY_IN_PROGRESS para siempre.
            $record->delete();

            throw $e;
        }

        if ($response->getStatusCode() < 400) {
            $record->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => json_decode((string) $response->getContent(), true),
                'locked_at' => null,
            ]);
        } else {
            // Un fallo no consume la clave: el cliente debe poder corregir y
            // reintentar con la misma.
            $record->delete();
        }

        return $response;
    }

    /**
     * sha256 del JSON NORMALIZADO (claves ordenadas), no del texto crudo: un espacio
     * de mas no debe contar como cuerpo distinto.
     */
    private function hashBody(Request $request): string
    {
        $payload = $request->all();
        $this->ksortRecursive($payload);

        return hash('sha256', (string) json_encode($payload));
    }

    /** @param  array<mixed>  $array */
    private function ksortRecursive(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }
}
