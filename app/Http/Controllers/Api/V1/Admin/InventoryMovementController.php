<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MovementIndexRequest;
use App\Http\Resources\Api\V1\MovementResource;
use App\Http\Resources\Api\V1\Paginated;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * El libro mayor. Es la herramienta de auditoria del inventario.
 */
class InventoryMovementController extends Controller
{
    public function index(MovementIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = InventoryMovement::query()
            ->with(['inventoryItem.variant', 'inventoryItem.warehouse', 'actor']);

        if (isset($filters['sku'])) {
            $query->whereHas('inventoryItem.variant', fn (Builder $q) => $q->where('sku', $filters['sku']));
        }

        if (isset($filters['warehouse_code'])) {
            $query->whereHas('inventoryItem.warehouse', fn (Builder $q) => $q->where('code', $filters['warehouse_code']));
        }

        if (isset($filters['type'])) {
            $query->whereIn('type', array_map('trim', explode(',', $filters['type'])));
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $query->orderBy('created_at', ($filters['sort'] ?? '-created_at') === 'created_at' ? 'asc' : 'desc');

        $paginator = $query->paginate(
            perPage: (int) ($filters['per_page'] ?? 50),
            page: (int) ($filters['page'] ?? 1),
        )->withQueryString();

        return response()->json(
            Paginated::wrap($paginator, MovementResource::collection($paginator->getCollection()))
        );
    }
}
