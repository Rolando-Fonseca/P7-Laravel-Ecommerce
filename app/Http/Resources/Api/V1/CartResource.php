<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'items' => $this->items->map(fn (CartItem $item): array => [
                'id' => $item->id,
                'sku' => $item->variant->sku,
                'product' => [
                    'slug' => $item->variant->product->slug,
                    'name' => $item->variant->product->name,
                ],
                'variant_label' => $item->variant->label(),
                'image_url' => $item->variant->product->primaryImage?->url,
                'quantity' => $item->quantity,
                // Precio congelado al anadir. No se recalcula al consultar.
                'unit_price_cents' => $item->unit_price_cents,
                'line_total_cents' => $item->lineTotalCents(),
                // Informativos: el carrito no bloquea nada (ADR-0005). Sirven para
                // avisar antes de la pantalla de pago.
                'available' => $item->variant->available(),
                'has_enough_stock' => $item->variant->available() >= $item->quantity,
            ])->values()->all(),
            'totals' => [
                'subtotal_cents' => $this->subtotalCents(),
                // ADR-0008: los tres valen siempre 0 en el MVP y estan desde el dia
                // uno para que anadirlos no sea un cambio incompatible.
                'discount_cents' => $this->discountCents(),
                'shipping_cents' => $this->shippingCents(),
                'tax_cents' => $this->taxCents(),
                'total_cents' => $this->totalCents(),
            ],
            'item_count' => $this->itemCount(),
            'expires_at' => $this->expires_at->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
