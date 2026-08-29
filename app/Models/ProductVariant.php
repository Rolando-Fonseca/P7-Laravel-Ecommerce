<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SizeSystem;
use App\Enums\VariantStatus;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'size', 'size_system', 'color_name', 'color_hex',
        'price_cents', 'barcode', 'weight_grams', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => VariantStatus::class,
            'size_system' => SizeSystem::class,
            'price_cents' => 'integer',
            'weight_grams' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'sku';
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<InventoryItem, $this> */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * Precio efectivo. El cliente nunca hace este ?? : se resuelve en el servidor.
     */
    public function effectivePriceCents(): int
    {
        return $this->price_cents ?? $this->product->base_price_cents;
    }

    /** Suma de (on_hand - reserved) en todos los almacenes. */
    public function available(): int
    {
        return (int) $this->inventoryItems->sum(
            fn (InventoryItem $item): int => $item->available
        );
    }

    public function label(): string
    {
        return "{$this->color_name} / {$this->size}";
    }

    public function isSellable(): bool
    {
        return $this->status === VariantStatus::Active
            && $this->product->status->value === 'active';
    }
}
