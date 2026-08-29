<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Libro mayor de inventario. APPEND-ONLY: no tiene updated_at porque nunca se
 * actualiza, y nunca se borra.
 */
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'inventory_item_id', 'type', 'quantity_delta', 'reason',
        'reference_type', 'reference_id', 'actor_id', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['type' => MovementType::class, 'quantity_delta' => 'integer'];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
