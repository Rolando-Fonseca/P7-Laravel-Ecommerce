<?php

declare(strict_types=1);

namespace App\Exceptions;

class CartExpiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Este carrito caducó. Crea uno nuevo.');
    }

    public function code(): string
    {
        return 'CART_EXPIRED';
    }

    /** 410 Gone: existió y ya no es utilizable. */
    public function httpStatus(): int
    {
        return 410;
    }
}
