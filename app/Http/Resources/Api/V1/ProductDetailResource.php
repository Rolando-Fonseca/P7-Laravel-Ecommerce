<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'category' => [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ],
            'material' => $this->material,
            'care_instructions' => $this->care_instructions,
            'base_price_cents' => $this->base_price_cents,
            'currency' => $this->currency,
            'size_system' => $this->category->size_system->value,
            'images' => $this->images->map(fn (ProductImage $i): array => [
                'url' => $i->url,
                'alt' => $i->alt,
                'position' => $i->position,
            ])->all(),
            'variants' => $this->variants->map(fn (ProductVariant $v): array => [
                'sku' => $v->sku,
                'size' => $v->size,
                'color' => ['name' => $v->color_name, 'hex' => $v->color_hex],
                // Ya viene resuelto: si la variante no tiene precio propio, aqui
                // aparece el del producto. El cliente nunca hace ese ??.
                'price_cents' => $v->effectivePriceCents(),
                'status' => $v->status->value,
                'available' => $v->available(),
            ])->all(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
