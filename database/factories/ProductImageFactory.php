<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductImage> */
class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'url' => 'https://cdn.nogal.store/p/'.fake()->uuid().'.webp',
            'alt' => 'Prenda sobre fondo neutro',
            'position' => 1,
            'is_primary' => true,
        ];
    }
}
