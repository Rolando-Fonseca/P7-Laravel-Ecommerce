<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000000, 9000000);

        return [
            'number' => 'NGL-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'user_id' => null,
            'cart_id' => null,
            'status' => 'created',
            'email' => fake()->safeEmail(),
            'subtotal_cents' => $subtotal,
            'discount_cents' => 0,
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => $subtotal,
            'currency' => 'COP',
            'shipping_address' => [
                'full_name' => fake()->name(),
                'phone' => '+57 300 123 4567',
                'line1' => fake()->streetAddress(),
                'city' => 'Medellin',
                'state' => 'Antioquia',
                'country' => 'CO',
            ],
            'billing_address' => null,
            'notes' => null,
            'status_history' => [[
                'status' => 'created',
                'at' => now()->toIso8601ZuluString(),
                'by' => null,
            ]],
            'placed_at' => now(),
        ];
    }
}
