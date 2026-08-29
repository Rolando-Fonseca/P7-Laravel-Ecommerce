<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Exceptions\CartExpiredException;
use App\Exceptions\CartNotModifiableException;
use App\Exceptions\VariantUnavailableException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CartService
{
    public function create(?int $userId = null): Cart
    {
        return Cart::create([
            'token' => (string) Str::uuid(),
            'user_id' => $userId,
            'currency' => 'COP',
            'status' => 'open',
            'expires_at' => now()->addDays(Cart::LIFETIME_DAYS),
        ]);
    }

    /**
     * Anadir una variante ya presente SUMA cantidad. Sin esto terminas con
     * "Camisa Azul M x1" tres veces seguidas y el usuario no entiende que compro.
     *
     * Este metodo NO valida stock. Es la decision de ADR-0005: anadir al carrito no
     * compromete inventario, asi que no puede fallar por falta de el.
     */
    public function addItem(Cart $cart, ProductVariant $variant, int $quantity): Cart
    {
        $this->assertModifiable($cart);

        if (! $variant->isSellable()) {
            throw new VariantUnavailableException($variant->sku);
        }

        DB::transaction(function () use ($cart, $variant, $quantity): void {
            $existing = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            $newQuantity = ($existing?->quantity ?? 0) + $quantity;

            if ($newQuantity > CartItem::MAX_QUANTITY) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'La cantidad total por linea no puede superar %d. Ya tienes %d en el carrito.',
                        CartItem::MAX_QUANTITY,
                        $existing?->quantity ?? 0
                    ),
                ]);
            }

            if ($existing !== null) {
                $existing->update(['quantity' => $newQuantity]);

                return;
            }

            CartItem::create([
                'cart_id' => $cart->id,
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
                // Precio congelado. No se recalcula al consultar: si el total
                // cambiara solo entre que el usuario lo mira y confirma, habria
                // reclamos.
                'unit_price_cents' => $variant->effectivePriceCents(),
            ]);
        });

        return $cart->fresh(['items.variant.product']);
    }

    /** Reemplaza la cantidad, no la suma. Para borrar existe DELETE. */
    public function updateItem(Cart $cart, CartItem $item, int $quantity): Cart
    {
        $this->assertModifiable($cart);

        $item->update(['quantity' => $quantity]);

        return $cart->fresh(['items.variant.product']);
    }

    public function removeItem(Cart $cart, CartItem $item): Cart
    {
        $this->assertModifiable($cart);

        $item->delete();

        return $cart->fresh(['items.variant.product']);
    }

    public function clear(Cart $cart): Cart
    {
        $this->assertModifiable($cart);

        $cart->items()->delete();

        return $cart->fresh(['items.variant.product']);
    }

    /**
     * Un carrito converted que recibe un POST /items corrompe un pedido ya creado.
     * De ahi que el estado no sea decorativo.
     */
    public function assertModifiable(Cart $cart): void
    {
        if ($cart->isExpired()) {
            throw new CartExpiredException;
        }

        if (! $cart->status->isModifiable()) {
            throw new CartNotModifiableException($cart->status);
        }
    }
}
