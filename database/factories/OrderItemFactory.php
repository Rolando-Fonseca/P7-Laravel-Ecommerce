<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $price = 1890000;

        return [
            'order_id' => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'sku' => 'NGL-TST-'.strtoupper(fake()->unique()->bothify('???-##')),
            'product_name' => 'Camisa de prueba',
            'variant_label' => 'Azul cielo / M',
            'quantity' => 1,
            'unit_price_cents' => $price,
            'line_total_cents' => $price,
        ];
    }
}
