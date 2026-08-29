<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resumen para el historial: sin lineas, sin direcciones.
 *
 * Un historial de 20 pedidos con todas sus lineas y direcciones son cientos de
 * kilobytes que la pantalla de historial no usa.
 *
 * @mixin Order
 */
class OrderSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'item_count' => (int) $this->items->sum('quantity'),
            'total_cents' => $this->total_cents,
            'currency' => $this->currency,
            'placed_at' => $this->placed_at?->toIso8601ZuluString(),
        ];
    }
}
