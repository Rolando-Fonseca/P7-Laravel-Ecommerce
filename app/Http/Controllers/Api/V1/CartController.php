<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cart\CartService;
use App\Exceptions\CartExpiredException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
    ) {}

    public function store(Request $request): JsonResponse
    {
        // currency no se acepta del cliente: es siempre COP y lo decide el servidor.
        // Aceptarla abriria la puerta a que pida USD y reciba precios en pesos
        // etiquetados como dolares.
        $cart = $this->carts->create($request->user()?->id);

        return response()
            ->json(['data' => new CartResource($cart->load('items'))], 201)
            ->header('Location', "/api/v1/carts/{$cart->token}");
    }

    public function show(string $token): JsonResponse
    {
        $cart = $this->findCart($token);

        if ($cart->isExpired()) {
            throw new CartExpiredException;
        }

        return response()->json(['data' => new CartResource($cart)]);
    }

    /** Vacia el carrito. No lo borra: sigue siendo el mismo token. */
    public function destroy(string $token): JsonResponse
    {
        $cart = $this->findCart($token);

        return response()->json(['data' => new CartResource($this->carts->clear($cart))]);
    }

    private function findCart(string $token): Cart
    {
        $cart = Cart::query()
            ->where('token', $token)
            ->with(['items.variant.product.primaryImage', 'items.variant.inventoryItems'])
            ->first();

        if ($cart === null) {
            throw new NotFoundHttpException;
        }

        return $cart;
    }
}
