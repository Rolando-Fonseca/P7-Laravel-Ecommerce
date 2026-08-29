<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'email' => $this->email,
            'items' => $this->items->map(fn (OrderItem $item): array => [
                'sku' => $item->sku,
                'product_name' => $item->product_name,
                'variant_label' => $item->variant_label,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'line_total_cents' => $item->line_total_cents,
            ])->values()->all(),
            'totals' => [
                'subtotal_cents' => $this->subtotal_cents,
                'discount_cents' => $this->discount_cents,
                'shipping_cents' => $this->shipping_cents,
                'tax_cents' => $this->tax_cents,
                'total_cents' => $this->total_cents,
            ],
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,
            'notes' => $this->notes,
            // Se expone para que un panel pinte solo los botones legales, en vez de
            // mantener una copia de la maquina de estados en el cliente. Esa copia
            // se desincroniza siempre.
            'allowed_transitions' => $this->status->allowedTransitionValues(),
            'status_history' => $this->status_history,
            'placed_at' => $this->placed_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
