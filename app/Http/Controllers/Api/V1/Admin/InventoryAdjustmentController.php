<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Inventory\InventoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdjustInventoryRequest;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

class InventoryAdjustmentController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function store(AdjustInventoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $variant = ProductVariant::where('sku', $data['sku'])->firstOrFail();
        $warehouse = Warehouse::where('code', $data['warehouse_code'])->firstOrFail();

        $movement = $this->inventory->adjust(
            variant: $variant,
            warehouse: $warehouse,
            delta: (int) $data['quantity_delta'],
            reason: $data['reason'],
            actorId: $request->user()?->id,
            // Se copia a inventory_movements.idempotency_key, que es unique. Es una
            // segunda red: aunque falle la capa de idempotencia, la base de datos
            // rechaza el movimiento duplicado.
            idempotencyKey: $request->header('Idempotency-Key'),
        );

        $item = $movement->inventoryItem->fresh();
        $actor = $request->user();

        return response()->json([
            'data' => [
                'movement_id' => $movement->id,
                'sku' => $variant->sku,
                'warehouse_code' => $warehouse->code,
                'type' => $movement->type->value,
                'quantity_delta' => $movement->quantity_delta,
                'reason' => $movement->reason,
                // Aqui SI se exponen on_hand y reserved: es la zona de
                // administracion y el operario necesita ver el efecto exacto de lo
                // que acaba de hacer.
                'resulting_stock' => [
                    'quantity_on_hand' => $item->quantity_on_hand,
                    'quantity_reserved' => $item->quantity_reserved,
                    'available' => $item->available,
                ],
                'created_by' => $actor === null ? null : ['id' => $actor->id, 'name' => $actor->name],
                'created_at' => $movement->created_at?->toIso8601ZuluString(),
            ],
        ], 201);
    }
}
