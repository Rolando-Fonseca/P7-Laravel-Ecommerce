<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductListResource extends JsonResource
{
    /**
     * El listado NO incluye las variantes completas: devuelve tallas, colores y
     * rango de precio, que es lo unico que la tarjeta necesita. Incluir 10 variantes
     * por producto multiplica por seis el tamano de la respuesta para un dato que la
     * vista de listado no usa.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variants = $this->whenLoaded('variants');
        $prices = $this->relationLoaded('variants')
            ? $this->variants->map(fn (ProductVariant $v): int => $v->price_cents ?? $this->base_price_cents)
            : collect([$this->base_price_cents]);

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'summary' => $this->summary,
            'category' => [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ],
            'material' => $this->material,
            'base_price_cents' => $this->base_price_cents,
            'currency' => $this->currency,
            'price_range_cents' => [
                'min' => (int) $prices->min(),
                'max' => (int) $prices->max(),
            ],
            'primary_image' => $this->primaryImage === null ? null : [
                'url' => $this->primaryImage->url,
                'alt' => $this->primaryImage->alt,
            ],
            'available_sizes' => $this->relationLoaded('variants')
                ? $this->variants->pluck('size')->unique()->values()->all()
                : [],
            'available_colors' => $this->relationLoaded('variants')
                ? $this->variants->unique('color_name')->values()->map(fn (ProductVariant $v): array => [
                    'name' => $v->color_name,
                    'hex' => $v->color_hex,
                ])->all()
                : [],
            'in_stock' => $this->relationLoaded('variants')
                ? $this->variants->contains(fn (ProductVariant $v): bool => $v->available() > 0)
                : null,
            'variant_count' => $this->relationLoaded('variants') ? $this->variants->count() : null,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
