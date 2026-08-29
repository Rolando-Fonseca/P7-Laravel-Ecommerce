<?php

declare(strict_types=1);

namespace App\Exceptions;

class IdempotencyKeyRequiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Esta operación requiere el header Idempotency-Key.');

        $this->details = [[
            'field' => 'Idempotency-Key',
            'issue' => 'header obligatorio ausente',
        ]];
    }

    public function code(): string
    {
        return 'IDEMPOTENCY_KEY_REQUIRED';
    }

    public function httpStatus(): int
    {
        return 400;
    }
}
