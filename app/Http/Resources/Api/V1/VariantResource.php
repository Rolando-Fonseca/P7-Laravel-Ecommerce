<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\InventoryItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class VariantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'product' => [
                'slug' => $this->product->slug,
                'name' => $this->product->name,
            ],
            'size' => $this->size,
            'size_system' => $this->size_system->value,
            'color' => ['name' => $this->color_name, 'hex' => $this->color_hex],
            'price_cents' => $this->effectivePriceCents(),
            'currency' => $this->product->currency,
            'barcode' => $this->barcode,
            'weight_grams' => $this->weight_grams,
            'status' => $this->status->value,
            'stock' => [
                'available' => $this->available(),
                'by_warehouse' => $this->inventoryItems->map(fn (InventoryItem $i): array => [
                    'warehouse_code' => $i->warehouse->code,
                    'available' => $i->available,
                ])->all(),
            ],
        ];
    }
}
