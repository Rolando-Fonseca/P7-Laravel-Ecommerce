<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Inventory\InventoryService;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Cambiar el estado de un pedido y mover el stock son la misma operacion. Nunca se
 * hace una sin la otra, y por eso viven en la misma transaccion.
 */
final class OrderTransitionService
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function transition(
        Order $order,
        OrderStatus $target,
        ?string $reason = null,
        ?int $actorId = null,
    ): Order {
        return DB::transaction(function () use ($order, $target, $reason, $actorId): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Unico punto del sistema que decide si una transicion es legal.
            if (! $order->status->canTransitionTo($target)) {
                throw new InvalidStateTransitionException($order->status, $target);
            }

            $this->applyStockEffect($order, $target);

            $history = $order->status_history;
            $history[] = [
                'status' => $target->value,
                'at' => now()->toIso8601ZuluString(),
                'by' => $actorId !== null ? "admin:{$actorId}" : null,
                'reason' => $reason,
            ];

            $order->update([
                'status' => $target,
                'status_history' => $history,
                'cancelled_at' => $target === OrderStatus::Cancelled ? now() : $order->cancelled_at,
            ]);

            return $order->fresh('items');
        });
    }

    /**
     * La tabla de docs/domain/04-pedidos.md, implementada.
     *
     * paid y packed no mueven inventario: el stock ya esta reservado desde que se
     * creo el pedido y sigue fisicamente en el almacen.
     */
    private function applyStockEffect(Order $order, OrderStatus $target): void
    {
        if (! in_array($target, [OrderStatus::Shipped, OrderStatus::Cancelled, OrderStatus::Returned], true)) {
            return;
        }

        $orderItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->whereNotNull('product_variant_id')
            ->get();

        $variantIds = $orderItems->pluck('product_variant_id')->all();
        $itemsByVariant = $this->inventory->lockItemsForVariants($variantIds);

        foreach ($orderItems as $orderItem) {
            $items = $itemsByVariant->get($orderItem->product_variant_id, collect());

            match ($target) {
                OrderStatus::Shipped => $this->inventory->ship($items, $orderItem->quantity, $order->number),
                OrderStatus::Cancelled => $this->inventory->release($items, $orderItem->quantity, $order->number),
                OrderStatus::Returned => $this->inventory->returnStock($items, $orderItem->quantity, $order->number),
                default => null,
            };
        }
    }
}
