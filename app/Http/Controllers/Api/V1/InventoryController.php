<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\VariantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AvailabilityRequest;
use App\Models\InventoryItem;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InventoryController extends Controller
{
    /**
     * La respuesta publica NO expone quantity_on_hand ni quantity_reserved. Esas dos
     * cantidades revelan volumen de ventas en curso: un competidor que consulta
     * reserved cada hora sabe exactamente cuanto vendes.
     */
    public function show(string $sku): JsonResponse
    {
        $variant = ProductVariant::query()
            ->where('sku', $sku)
            ->where('status', VariantStatus::Active)
            ->with('inventoryItems.warehouse')
            ->first();

        if ($variant === null) {
            throw new NotFoundHttpException;
        }

        return response()->json([
            'data' => [
                'sku' => $variant->sku,
                'available' => $variant->available(),
                'in_stock' => $variant->available() > 0,
                'by_warehouse' => $variant->inventoryItems->map(fn (InventoryItem $i): array => [
                    'warehouse_code' => $i->warehouse->code,
                    'available' => $i->available,
                ])->all(),
            ],
        ]);
    }

    /**
     * Consulta en lote: existe para que una pagina de producto con 10 variantes no
     * haga 10 peticiones.
     */
    public function availability(AvailabilityRequest $request): JsonResponse
    {
        $skus = $request->validated()['skus'];

        $variants = ProductVariant::query()
            ->whereIn('sku', $skus)
            ->where('status', VariantStatus::Active)
            ->with('inventoryItems')
            ->get()
            ->keyBy('sku');

        $data = array_map(function (string $sku) use ($variants): array {
            $variant = $variants->get($sku);

            // Un sku inexistente devuelve available null en lugar de 404. La
            // peticion en lote es valida aunque un elemento no lo sea; devolver 404
            // por un sku obsoleto en cache romperia la pagina entera.
            return [
                'sku' => $sku,
                'available' => $variant?->available(),
                'in_stock' => ($variant?->available() ?? 0) > 0,
            ];
        }, $skus);

        return response()->json(['data' => $data]);
    }
}
