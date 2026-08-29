<?php

declare(strict_types=1);

namespace App\Exceptions;

class IdempotencyKeyReusedException extends DomainException
{
    public function __construct(string $key)
    {
        parent::__construct('La clave de idempotencia ya se usó con un cuerpo distinto.');

        $this->details = [[
            'field' => 'Idempotency-Key',
            'issue' => 'clave reutilizada con un cuerpo diferente',
            'meta' => ['key' => $key],
        ]];
    }

    public function code(): string
    {
        return 'IDEMPOTENCY_KEY_REUSED';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
