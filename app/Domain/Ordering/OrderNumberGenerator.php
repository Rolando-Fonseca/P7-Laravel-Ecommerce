<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Models\Order;

/**
 * NGL-2026-000123: prefijo de marca, ano, secuencia. Es lo que va en la URL, en el
 * correo y en lo que el cliente dice por telefono. El id autoincremental nunca sale
 * de la base de datos.
 *
 * Se invoca siempre dentro de la transaccion de CreateOrderService, con las filas
 * de orders ya bajo bloqueo, asi que la secuencia no se puede duplicar.
 */
final class OrderNumberGenerator
{
    public const PREFIX = 'NGL';

    public function next(): string
    {
        $year = now()->year;
        $prefix = self::PREFIX."-{$year}-";

        $last = Order::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null
            ? 1
            : ((int) substr((string) $last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
