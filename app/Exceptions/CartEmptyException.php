<?php

declare(strict_types=1);

namespace App\Exceptions;

class CartEmptyException extends DomainException
{
    public function __construct()
    {
        parent::__construct('No se puede crear un pedido con el carrito vacío.');

        $this->details = [[
            'field' => 'cart_token',
            'issue' => 'el carrito no tiene líneas',
        ]];
    }

    public function code(): string
    {
        return 'CART_EMPTY';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
