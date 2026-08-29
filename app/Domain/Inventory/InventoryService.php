<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Unico punto del sistema donde cambia el stock.
 *
 * Regla que no se negocia: toda modificacion de quantity_on_hand o
 * quantity_reserved escribe su fila en inventory_movements dentro de la MISMA
 * transaccion. Si esa igualdad se rompe, hay un UPDATE que se salto el libro mayor.
 */
final class InventoryService
{
    /**
     * Ajuste manual de administracion: recepcion, correccion de conteo, merma.
     */
    public function adjust(
        ProductVariant $variant,
        Warehouse $warehouse,
        int $delta,
        string $reason,
        ?int $actorId = null,
        ?string $idempotencyKey = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($variant, $warehouse, $delta, $reason, $actorId, $idempotencyKey): InventoryMovement {
            $item = InventoryItem::query()
                ->where('product_variant_id', $variant->id)
                ->where('warehouse_id', $warehouse->id)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                $item = InventoryItem::create([
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]);
            }

            $resulting = $item->quantity_on_hand + $delta;

            // Protege la invariante reserved <= on_hand. Si hay 3 unidades
            // comprometidas por pedidos en curso, no se puede bajar el stock a 1:
            // esos pedidos ya se reservaron.
            if ($resulting < $item->quantity_reserved) {
                throw new InsufficientStockException([[
                    'field' => 'quantity_delta',
                    'issue' => sprintf(
                        'el ajuste dejaria %d unidades y hay %d reservadas',
                        $resulting,
                        $item->quantity_reserved
                    ),
                    'meta' => [
                        'sku' => $variant->sku,
                        'quantity_on_hand' => $item->quantity_on_hand,
                        'quantity_reserved' => $item->quantity_reserved,
                    ],
                ]], 'El ajuste dejaria menos unidades de las que hay reservadas.');
            }

            $item->update(['quantity_on_hand' => $resulting]);

            return $this->recordMovement(
                $item,
                MovementType::Adjustment,
                $delta,
                $reason,
                actorId: $actorId,
                idempotencyKey: $idempotencyKey,
            );
        });
    }

    /**
     * Bloquea las existencias de las variantes indicadas y las agrupa por variante.
     *
     * ADR-0006: el ORDER BY id es la parte importante, no un detalle de estilo. Si
     * el pedido A bloquea la variante 10 y luego la 20, y el pedido B bloquea la 20
     * y luego la 10, se esperan mutuamente para siempre. Con un orden fijo los dos
     * las piden igual y uno simplemente espera al otro.
     *
     * @param  array<int, int>  $variantIds
     * @return Collection<int, Collection<int, InventoryItem>>
     */
    public function lockItemsForVariants(array $variantIds): Collection
    {
        return InventoryItem::query()
            ->whereIn('product_variant_id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('product_variant_id');
    }

    /**
     * Valida que haya disponible suficiente para TODAS las lineas y devuelve el
     * reparto por existencia.
     *
     * Acumula todos los fallos antes de lanzar: si el usuario tiene tres problemas,
     * tiene que verlos los tres a la vez. Mostrarle uno, que lo arregle y mostrarle
     * el siguiente es la peor experiencia posible en una pantalla de pago.
     *
     * @param  array<int, array{variant: ProductVariant, quantity: int}>  $lines
     * @return array<int, array{item: InventoryItem, quantity: int}>
     */
    public function allocateForReservation(array $lines, Collection $itemsByVariant): array
    {
        $allocation = [];
        $failures = [];

        foreach ($lines as $index => $line) {
            $variant = $line['variant'];
            $needed = $line['quantity'];

            /** @var Collection<int, InventoryItem> $items */
            $items = $itemsByVariant->get($variant->id, collect());
            $available = (int) $items->sum(fn (InventoryItem $i): int => $i->available);

            if ($available < $needed) {
                $failures[] = [
                    'field' => "items.{$index}.quantity",
                    'issue' => "solicitado {$needed}, disponible {$available}",
                    'meta' => [
                        'sku' => $variant->sku,
                        'product_name' => $variant->product->name,
                        'variant_label' => $variant->label(),
                        'requested' => $needed,
                        'available' => $available,
                    ],
                ];

                continue;
            }

            $remaining = $needed;
            foreach ($items as $item) {
                if ($remaining === 0) {
                    break;
                }

                $take = min($remaining, $item->available);
                if ($take <= 0) {
                    continue;
                }

                $allocation[] = ['item' => $item, 'quantity' => $take];
                $remaining -= $take;
            }
        }

        if ($failures !== []) {
            throw new InsufficientStockException(
                $failures,
                'No hay unidades suficientes para completar el pedido.'
            );
        }

        return $allocation;
    }

    /** @param  array<int, array{item: InventoryItem, quantity: int}>  $allocation */
    public function reserve(array $allocation, string $orderNumber): void
    {
        foreach ($allocation as $entry) {
            $item = $entry['item'];
            $item->update(['quantity_reserved' => $item->quantity_reserved + $entry['quantity']]);

            $this->recordMovement(
                $item,
                MovementType::Reservation,
                -$entry['quantity'],
                "Reserva por pedido {$orderNumber}",
                referenceType: 'Order',
                referenceId: $orderNumber,
            );
        }
    }

    /**
     * Cancelacion: libera la reserva. El stock fisico no se toca porque nunca llego
     * a salir del almacen.
     *
     * @param  Collection<int, InventoryItem>  $items
     */
    public function release(Collection $items, int $quantity, string $orderNumber): void
    {
        $this->drawFromReserved($items, $quantity, function (InventoryItem $item, int $take) use ($orderNumber): void {
            $item->update(['quantity_reserved' => $item->quantity_reserved - $take]);

            $this->recordMovement(
                $item,
                MovementType::Release,
                $take,
                "Liberacion por cancelacion del pedido {$orderNumber}",
                referenceType: 'Order',
                referenceId: $orderNumber,
            );
        });
    }

    /**
     * Despacho: aqui, y solo aqui, se descuenta el stock fisico. Mientras el paquete
     * estaba en el almacen la unidad seguia existiendo: estaba reservada, no vendida.
     * Confundir estos dos momentos es el motivo mas frecuente de descuadres.
     *
     * @param  Collection<int, InventoryItem>  $items
     */
    public function ship(Collection $items, int $quantity, string $orderNumber): void
    {
        $this->drawFromReserved($items, $quantity, function (InventoryItem $item, int $take) use ($orderNumber): void {
            $item->update([
                'quantity_on_hand' => $item->quantity_on_hand - $take,
                'quantity_reserved' => $item->quantity_reserved - $take,
            ]);

            $this->recordMovement(
                $item,
                MovementType::Sale,
                -$take,
                "Despacho del pedido {$orderNumber}",
                referenceType: 'Order',
                referenceId: $orderNumber,
            );
        });
    }

    /** @param  Collection<int, InventoryItem>  $items */
    public function returnStock(Collection $items, int $quantity, string $orderNumber): void
    {
        $item = $items->first();

        if ($item === null) {
            return;
        }

        $item->update(['quantity_on_hand' => $item->quantity_on_hand + $quantity]);

        $this->recordMovement(
            $item,
            MovementType::ReturnIn,
            $quantity,
            "Devolucion del pedido {$orderNumber}",
            referenceType: 'Order',
            referenceId: $orderNumber,
        );
    }

    /**
     * @param  Collection<int, InventoryItem>  $items
     * @param  callable(InventoryItem, int): void  $apply
     */
    private function drawFromReserved(Collection $items, int $quantity, callable $apply): void
    {
        $remaining = $quantity;

        foreach ($items as $item) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $item->quantity_reserved);
            if ($take <= 0) {
                continue;
            }

            $apply($item, $take);
            $remaining -= $take;
        }
    }

    private function recordMovement(
        InventoryItem $item,
        MovementType $type,
        int $delta,
        string $reason,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?int $actorId = null,
        ?string $idempotencyKey = null,
    ): InventoryMovement {
        return InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'type' => $type,
            'quantity_delta' => $delta,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'actor_id' => $actorId,
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
