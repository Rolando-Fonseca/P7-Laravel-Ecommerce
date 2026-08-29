<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cart\CartService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddCartItemRequest;
use App\Http\Requests\Api\V1\UpdateCartItemRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartItemController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
    ) {}

    /**
     * Devuelve 200 y no 201 a proposito: el recurso que devuelve es el carrito, que
     * ya existia. Lo que se creo es una linea, que no tiene URL propia.
     */
    public function store(AddCartItemRequest $request, string $token): JsonResponse
    {
        $cart = $this->findCart($token);
        $data = $request->validated();

        $variant = ProductVariant::where('sku', $data['sku'])->with('product')->firstOrFail();

        $cart = $this->carts->addItem($cart, $variant, (int) $data['quantity']);

        return response()->json(['data' => new CartResource($this->reload($cart))]);
    }

    public function update(UpdateCartItemRequest $request, string $token, int $itemId): JsonResponse
    {
        $cart = $this->findCart($token);
        $item = $this->findItem($cart, $itemId);

        $cart = $this->carts->updateItem($cart, $item, (int) $request->validated()['quantity']);

        return response()->json(['data' => new CartResource($this->reload($cart))]);
    }

    /**
     * Devuelve el carrito y no un 204 porque el cliente necesita los totales
     * recalculados. Un 204 obligaria a un GET inmediato despues, siempre.
     */
    public function destroy(string $token, int $itemId): JsonResponse
    {
        $cart = $this->findCart($token);
        $item = $this->findItem($cart, $itemId);

        $cart = $this->carts->removeItem($cart, $item);

        return response()->json(['data' => new CartResource($this->reload($cart))]);
    }

    private function findCart(string $token): Cart
    {
        $cart = Cart::where('token', $token)->first();

        if ($cart === null) {
            throw new NotFoundHttpException;
        }

        return $cart;
    }

    /**
     * Si el itemId existe pero pertenece a otro carrito, la respuesta es 404 y no
     * 403. Un 403 confirmaria que la linea existe y permitiria enumerar el contenido
     * de otros carritos.
     */
    private function findItem(Cart $cart, int $itemId): CartItem
    {
        $item = CartItem::where('id', $itemId)->where('cart_id', $cart->id)->first();

        if ($item === null) {
            throw new NotFoundHttpException;
        }

        return $item;
    }

    private function reload(Cart $cart): Cart
    {
        return $cart->load(['items.variant.product.primaryImage', 'items.variant.inventoryItems']);
    }
}
