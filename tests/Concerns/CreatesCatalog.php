<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;

trait CreatesCatalog
{
    protected function warehouse(): Warehouse
    {
        return Warehouse::firstOrCreate(
            ['code' => 'NGL-CEN'],
            ['name' => 'Bodega Central', 'city' => 'Medellin', 'is_default' => true, 'is_active' => true]
        );
    }

    /**
     * @param  array<string, mixed>  $variantAttributes
     * @param  array<string, mixed>  $productAttributes
     */
    protected function variantWithStock(
        int $onHand = 10,
        int $reserved = 0,
        array $variantAttributes = [],
        array $productAttributes = [],
    ): ProductVariant {
        $product = Product::factory()->create($productAttributes);

        $variant = ProductVariant::factory()
            ->for($product)
            ->create($variantAttributes);

        InventoryItem::create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse()->id,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
        ]);

        return $variant->fresh(['product', 'inventoryItems']);
    }

    protected function cartWith(ProductVariant $variant, int $quantity = 1): Cart
    {
        $cart = Cart::factory()->create();

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price_cents' => $variant->effectivePriceCents(),
        ]);

        return $cart->fresh('items');
    }

    /** @return array<string, mixed> */
    protected function orderPayload(Cart $cart): array
    {
        return [
            'cart_token' => $cart->token,
            'email' => 'cliente@ejemplo.com',
            'shipping_address' => [
                'full_name' => 'Andres Molina',
                'phone' => '+57 300 123 4567',
                'line1' => 'Carrera 45 # 26-30',
                'city' => 'Medellin',
                'state' => 'Antioquia',
                'country' => 'CO',
            ],
        ];
    }
}
