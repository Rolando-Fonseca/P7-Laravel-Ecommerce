<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Máquina de estados del pedido.
 *
 * La tabla de transiciones de docs/domain/04-pedidos.md se implementa aquí, en un
 * único lugar. Ningún otro punto del código decide si una transición es legal.
 */
enum OrderStatus: string
{
    case Created = 'created';
    case Paid = 'paid';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Created => [self::Paid, self::Cancelled],
            self::Paid => [self::Packed, self::Cancelled],
            self::Packed => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Returned],
            self::Cancelled, self::Returned => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** @return array<int, string> */
    public function allowedTransitionValues(): array
    {
        return array_map(fn (self $s): string => $s->value, $this->allowedTransitions());
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Creado',
            self::Paid => 'Pagado',
            self::Packed => 'Preparado',
            self::Shipped => 'Enviado',
            self::Cancelled => 'Cancelado',
            self::Returned => 'Devuelto',
        };
    }
}
