<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\ProductQueryService;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductIndexRequest;
use App\Http\Resources\Api\V1\Paginated;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductListResource;
use App\Http\Resources\Api\V1\VariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductQueryService $products,
    ) {}

    public function index(ProductIndexRequest $request): JsonResponse
    {
        $paginator = $this->products->paginate($request->validated());
        $paginator->load(['variants.inventoryItems']);

        return response()->json(
            Paginated::wrap($paginator, ProductListResource::collection($paginator->getCollection()))
        );
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', ProductStatus::Active)
            ->with(['category', 'images', 'variants.inventoryItems'])
            ->first();

        // Un producto archived devuelve 404, no 410. Un 410 confirmaria que existio
        // y filtra informacion comercial: que se dejo de vender y cuando.
        if ($product === null) {
            throw new NotFoundHttpException;
        }

        return response()->json(['data' => new ProductDetailResource($product)]);
    }

    public function variant(string $slug, string $sku): JsonResponse
    {
        $variant = ProductVariant::query()
            ->where('sku', $sku)
            ->whereHas('product', fn ($q) => $q->where('slug', $slug)->where('status', ProductStatus::Active))
            ->with(['product', 'inventoryItems.warehouse'])
            ->first();

        if ($variant === null) {
            throw new NotFoundHttpException;
        }

        return response()->json(['data' => new VariantResource($variant)]);
    }
}
