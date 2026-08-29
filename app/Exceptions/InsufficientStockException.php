<?php

declare(strict_types=1);

namespace App\Exceptions;

class InsufficientStockException extends DomainException
{
    /**
     * @param  array<int, array<string, mixed>>  $details  Todas las líneas que fallan.
     */
    public function __construct(array $details, string $message = 'No hay unidades suficientes para completar la operación.')
    {
        parent::__construct($message);
        $this->details = $details;
    }

    public function code(): string
    {
        return 'INSUFFICIENT_STOCK';
    }

    /**
     * 409 y no 422: pedir 5 camisas es una petición válida. Lo que falla es el estado
     * del servidor, no el input. Un cliente reintenta un 409 y no reintenta un 422.
     */
    public function httpStatus(): int
    {
        return 409;
    }
}
