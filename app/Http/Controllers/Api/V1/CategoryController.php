<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Sin paginacion: son menos de 20 filas y siempre se piden todas.
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount(['products' => fn ($q) => $q->where('status', ProductStatus::Active)])
            ->orderBy('position')
            ->get();

        return response()->json(['data' => CategoryResource::collection($categories)]);
    }
}
