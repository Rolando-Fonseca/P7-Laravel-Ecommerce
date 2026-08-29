<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryItem> */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'reorder_point' => 3,
        ];
    }

    public function withStock(int $onHand, int $reserved = 0): static
    {
        return $this->state([
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
        ]);
    }
}
