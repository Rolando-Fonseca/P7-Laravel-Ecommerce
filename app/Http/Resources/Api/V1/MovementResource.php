<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryMovement
 */
class MovementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'movement_id' => $this->id,
            'sku' => $this->inventoryItem->variant->sku,
            'warehouse_code' => $this->inventoryItem->warehouse->code,
            'type' => $this->type->value,
            'quantity_delta' => $this->quantity_delta,
            'reason' => $this->reason,
            'reference' => $this->reference_type === null ? null : [
                'type' => $this->reference_type,
                'id' => $this->reference_id,
            ],
            // null cuando lo genero el sistema (una reserva por pedido); con usuario
            // cuando fue un ajuste manual.
            'created_by' => $this->actor === null ? null : [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ],
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
