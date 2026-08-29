<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Constructor unico de la envoltura de error de
 * docs/api/contracts/00-convenciones.md.
 *
 * Ningun controller construye respuestas de error a mano. Si lo hiciera, el dia que
 * cambie el formato habria que buscar en treinta archivos.
 */
final class ApiError
{
    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    public static function response(
        string $code,
        string $message,
        array $details = [],
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                // Siempre un array, aunque tenga un solo elemento. Un objeto
                // {"campo": "error"} no puede expresar dos errores sobre el mismo
                // campo, y la validacion de un carrito con 12 lineas los produce
                // constantemente.
                'details' => array_values($details),
                'traceId' => TraceId::current(),
            ],
        ], $status);
    }
}
