<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos del libro mayor de inventario. La tabla es append-only: un movimiento
 * describe qué cambió, cuánto y por qué.
 */
enum MovementType: string
{
    case Adjustment = 'adjustment';
    case Reservation = 'reservation';
    case Release = 'release';
    case Sale = 'sale';
    case ReturnIn = 'return';

    /**
     * Solo estos tipos alteran quantity_on_hand. Los demás mueven quantity_reserved.
     * La suma de sus deltas debe coincidir siempre con quantity_on_hand.
     */
    public function affectsOnHand(): bool
    {
        return in_array($this, [self::Adjustment, self::Sale, self::ReturnIn], true);
    }
}
