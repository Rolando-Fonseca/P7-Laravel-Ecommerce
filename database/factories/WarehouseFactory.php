<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'NGL-'.strtoupper(fake()->unique()->lexify('???')),
            'name' => 'Almacen '.fake()->city(),
            'city' => fake()->city(),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
