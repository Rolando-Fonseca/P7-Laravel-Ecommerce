<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryMovement> */
class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'type' => 'adjustment',
            'quantity_delta' => 10,
            'reason' => 'Carga inicial de prueba',
            'reference_type' => null,
            'reference_id' => null,
            'actor_id' => null,
            'idempotency_key' => null,
        ];
    }
}
