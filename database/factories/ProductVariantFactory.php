<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'NGL-TST-'.strtoupper(fake()->unique()->bothify('???-##??')),
            'size' => fake()->randomElement(['S', 'M', 'L', 'XL', 'XXL']),
            'size_system' => 'alpha',
            'color_name' => fake()->randomElement(['Azul cielo', 'Blanco hueso', 'Verde oliva', 'Negro']),
            'color_hex' => fake()->hexColor(),
            'price_cents' => null,
            'barcode' => fake()->ean13(),
            'weight_grams' => fake()->numberBetween(150, 900),
            'status' => 'active',
        ];
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }
}
