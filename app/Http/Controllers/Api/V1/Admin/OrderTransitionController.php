<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Ordering\OrderTransitionService;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TransitionOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * Un solo endpoint para las cinco transiciones.
 *
 * Cinco rutas (/pay, /pack, /ship...) son cinco controllers y cinco sitios donde
 * olvidar validar la transicion. Con uno solo, la maquina de estados vive en un
 * unico lugar y anadir un estado futuro no toca las rutas.
 *
 * El coste: la ruta es menos expresiva en un log de acceso. Vale la pena.
 */
class OrderTransitionController extends Controller
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
    ) {}

    public function store(TransitionOrderRequest $request, string $number): JsonResponse
    {
        $order = Order::where('number', $number)->firstOrFail();
        $data = $request->validated();

        $updated = $this->transitions->transition(
            order: $order,
            target: OrderStatus::from($data['to']),
            reason: $data['reason'] ?? null,
            actorId: $request->user()?->id,
        );

        return response()->json(['data' => new OrderResource($updated)]);
    }
}
