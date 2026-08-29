<?php

declare(strict_types=1);

namespace App\Exceptions;

class VariantUnavailableException extends DomainException
{
    public function __construct(string $sku)
    {
        parent::__construct('Esta referencia ya no está disponible.');

        $this->details = [[
            'field' => 'sku',
            'issue' => 'la variante o su producto no están activos',
            'meta' => ['sku' => $sku],
        ]];
    }

    public function code(): string
    {
        return 'VARIANT_UNAVAILABLE';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
