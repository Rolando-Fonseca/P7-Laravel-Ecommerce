<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\CartStatus;

class CartNotModifiableException extends DomainException
{
    public function __construct(CartStatus $status)
    {
        parent::__construct('Este carrito ya no se puede modificar.');

        $this->details = [[
            'field' => 'cart_token',
            'issue' => 'el carrito no está abierto',
            'meta' => ['status' => $status->value],
        ]];
    }

    public function code(): string
    {
        return 'CART_NOT_MODIFIABLE';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
