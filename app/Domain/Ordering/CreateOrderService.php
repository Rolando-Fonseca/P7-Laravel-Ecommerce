<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Cart\CartService;
use App\Domain\Inventory\InventoryService;
use App\Enums\CartStatus;
use App\Enums\OrderStatus;
use App\Exceptions\CartEmptyException;
use App\Exceptions\VariantUnavailableException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * La operacion critica del backend.
 *
 * Todo ocurre dentro de UNA transaccion. Si algo revienta al reservar stock, el
 * rollback deshace tambien el pedido ya insertado. Esa atomicidad es la razon de
 * que no haya varias transacciones encadenadas.
 */
final class CreateOrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CartService $carts,
        private readonly OrderNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $shippingAddress
     * @param  array<string, mixed>|null  $billingAddress
     */
    public function handle(
        Cart $cart,
        string $email,
        array $shippingAddress,
        ?array $billingAddress = null,
        ?string $notes = null,
        ?int $userId = null,
    ): Order {
        return DB::transaction(function () use ($cart, $email, $shippingAddress, $billingAddress, $notes, $userId): Order {
            // 1. Bloquear el carrito y validar que sigue siendo utilizable.
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $this->carts->assertModifiable($cart);

            $cartItems = CartItem::query()
                ->where('cart_id', $cart->id)
                ->with('variant.product')
                ->orderBy('id')
                ->get();

            if ($cartItems->isEmpty()) {
                throw new CartEmptyException;
            }

            // Una variante puede haberse archivado mientras el carrito esperaba.
            foreach ($cartItems as $item) {
                if (! $item->variant->isSellable()) {
                    throw new VariantUnavailableException($item->variant->sku);
                }
            }

            // 2. Bloquear las existencias, ordenadas por id (ADR-0006).
            $variantIds = $cartItems->pluck('product_variant_id')->all();
            $itemsByVariant = $this->inventory->lockItemsForVariants($variantIds);

            // 3. Validar disponibilidad de TODAS las lineas antes de escribir nada.
            $lines = $cartItems->values()->map(fn (CartItem $ci): array => [
                'variant' => $ci->variant,
                'quantity' => $ci->quantity,
            ])->all();

            $allocation = $this->inventory->allocateForReservation($lines, $itemsByVariant);

            // 4. Crear el pedido con los totales congelados.
            $number = $this->numbers->next();
            $subtotal = (int) $cartItems->sum(fn (CartItem $ci): int => $ci->lineTotalCents());

            $order = Order::create([
                'number' => $number,
                'user_id' => $userId,
                'cart_id' => $cart->id,
                'status' => OrderStatus::Created,
                'email' => $email,
                'subtotal_cents' => $subtotal,
                // ADR-0008: la formula se escribe entera aunque hoy los tres
                // sumandos valgan cero.
                'discount_cents' => 0,
                'shipping_cents' => 0,
                'tax_cents' => 0,
                'total_cents' => $subtotal - 0 + 0 + 0,
                'currency' => $cart->currency,
                'shipping_address' => $shippingAddress,
                'billing_address' => $billingAddress,
                'notes' => $notes,
                'status_history' => [[
                    'status' => OrderStatus::Created->value,
                    'at' => now()->toIso8601ZuluString(),
                    'by' => null,
                ]],
                'placed_at' => now(),
            ]);

            // 5. Copiar las lineas EN TEXTO. Si manana renombran el producto, este
            //    pedido debe seguir diciendo lo que el cliente compro.
            foreach ($cartItems as $ci) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $ci->product_variant_id,
                    'sku' => $ci->variant->sku,
                    'product_name' => $ci->variant->product->name,
                    'variant_label' => $ci->variant->label(),
                    'quantity' => $ci->quantity,
                    'unit_price_cents' => $ci->unit_price_cents,
                    'line_total_cents' => $ci->lineTotalCents(),
                ]);
            }

            // 6. Reservar el stock y escribir el libro mayor.
            $this->inventory->reserve($allocation, $number);

            // 7. El carrito deja de ser modificable.
            $cart->update(['status' => CartStatus::Converted]);

            return $order->load('items');
        });
    }
}
