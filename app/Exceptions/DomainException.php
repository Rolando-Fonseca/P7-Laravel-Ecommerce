<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base de toda excepción de negocio.
 *
 * Aporta el par (code, httpStatus) que el handler traduce al formato de error único
 * de docs/api/contracts/00-convenciones.md. Ningún controller construye respuestas
 * de error a mano: si lo hiciera, cambiar el formato obligaría a tocar 30 archivos.
 */
abstract class DomainException extends RuntimeException
{
    /** @var array<int, array<string, mixed>> */
    protected array $details = [];

    abstract public function code(): string;

    abstract public function httpStatus(): int;

    /** @return array<int, array<string, mixed>> */
    public function details(): array
    {
        return $this->details;
    }
}
