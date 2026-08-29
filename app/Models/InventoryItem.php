<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'product_variant_id', 'warehouse_id',
        'quantity_on_hand', 'quantity_reserved', 'reorder_point',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'reorder_point' => 'integer',
        ];
    }

    /**
     * Campo CALCULADO, nunca columna. Si fuera columna seria un tercer dato que
     * puede desincronizarse de los otros dos, y no habria forma de saber cual de
     * los tres miente.
     *
     * @return Attribute<int, never>
     */
    protected function available(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->quantity_on_hand - $this->quantity_reserved,
        );
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
