<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ordering\CreateOrderService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOrderRequest;
use App\Http\Requests\Api\V1\OrderIndexRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\OrderSummaryResource;
use App\Http\Resources\Api\V1\Paginated;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderController extends Controller
{
    public function __construct(
        private readonly CreateOrderService $createOrder,
    ) {}

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $cart = Cart::where('token', $data['cart_token'])->firstOrFail();

        $order = $this->createOrder->handle(
            cart: $cart,
            email: $data['email'],
            shippingAddress: $data['shipping_address'],
            billingAddress: $data['billing_address'] ?? null,
            notes: $data['notes'] ?? null,
            userId: $request->user()?->id,
        );

        return response()
            ->json(['data' => new OrderResource($order)], 201)
            ->header('Location', "/api/v1/orders/{$order->number}");
    }

    /**
     * Un invitado consulta con ?email=. Es un control debil a proposito: el numero
     * de pedido no es adivinable y exigir registro para ver una compra ya hecha es
     * hostil.
     */
    public function show(Request $request, string $number): JsonResponse
    {
        $order = Order::where('number', $number)->with('items')->first();

        if ($order === null) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();
        $email = (string) $request->query('email', '');

        $isOwner = $user !== null && $order->user_id === $user->id;
        $emailMatches = $email !== '' && hash_equals($order->email, $email);

        // Email que no coincide devuelve 404, no 403. Un 403 confirma que el pedido
        // existe y convierte el endpoint en un oraculo para adivinar numeros.
        if (! $isOwner && ! $emailMatches) {
            throw new NotFoundHttpException;
        }

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function index(OrderIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('items');

        if (isset($filters['status'])) {
            $query->whereIn('status', array_map('trim', explode(',', $filters['status'])));
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        [$column, $direction] = match ($filters['sort'] ?? '-created_at') {
            'created_at' => ['created_at', 'asc'],
            '-created_at' => ['created_at', 'desc'],
            'total' => ['total_cents', 'asc'],
            '-total' => ['total_cents', 'desc'],
        };

        $paginator = $query->orderBy($column, $direction)->paginate(
            perPage: (int) ($filters['per_page'] ?? 20),
            page: (int) ($filters['page'] ?? 1),
        )->withQueryString();

        return response()->json(
            Paginated::wrap($paginator, OrderSummaryResource::collection($paginator->getCollection()))
        );
    }
}
