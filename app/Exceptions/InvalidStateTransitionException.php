<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OrderStatus;

class InvalidStateTransitionException extends DomainException
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct(sprintf(
            'Un pedido en estado "%s" no puede pasar a "%s".',
            $from->label(),
            $to->label()
        ));

        $this->details = [[
            'field' => 'to',
            'issue' => 'transición no permitida',
            'meta' => [
                'current_status' => $from->value,
                'requested_status' => $to->value,
                'allowed_transitions' => $from->allowedTransitionValues(),
            ],
        ]];
    }

    public function code(): string
    {
        return 'INVALID_STATE_TRANSITION';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
