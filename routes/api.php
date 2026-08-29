<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\InventoryAdjustmentController;
use App\Http\Controllers\Api\V1\Admin\InventoryMovementController;
use App\Http\Controllers\Api\V1\Admin\OrderTransitionController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CartItemController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

/*
 * ADR-0002: la version va en el primer segmento de la ruta. Es visible en logs,
 * metricas, cache de CDN y en cualquier captura de red. Un header Accept-Version es
 * mas "correcto" segun REST y peor en la practica.
 *
 * El prefijo /api lo anade bootstrap/app.php, asi que estas rutas quedan en
 * /api/v1/...
 */
Route::prefix('v1')->group(function (): void {

    // --- Catalogo: lectura publica, sin riesgo ---
    Route::middleware('throttle:120,1')->group(function (): void {
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{slug}', [ProductController::class, 'show']);
        Route::get('products/{slug}/variants/{sku}', [ProductController::class, 'variant']);
        Route::get('inventory/{sku}', [InventoryController::class, 'show']);
    });

    Route::post('inventory/availability', [InventoryController::class, 'availability'])
        ->middleware('throttle:60,1');

    // --- Carrito: publico, la propiedad la da el token opaco ---
    Route::get('carts/{token}', [CartController::class, 'show'])->middleware('throttle:120,1');

    Route::middleware('throttle:60,1')->group(function (): void {
        Route::post('carts', [CartController::class, 'store']);
        Route::delete('carts/{token}', [CartController::class, 'destroy']);
        Route::post('carts/{token}/items', [CartItemController::class, 'store']);
        Route::patch('carts/{token}/items/{itemId}', [CartItemController::class, 'update']);
        Route::delete('carts/{token}/items/{itemId}', [CartItemController::class, 'destroy']);
    });

    // --- Pedidos ---
    // 10/min: crear pedidos es la operacion mas cara y la mas sensible a abuso.
    Route::post('orders', [OrderController::class, 'store'])
        ->middleware(['throttle:10,1', 'idempotency']);

    Route::get('orders/{number}', [OrderController::class, 'show'])->middleware('throttle:60,1');

    Route::get('orders', [OrderController::class, 'index'])
        ->middleware(['auth:sanctum', 'throttle:60,1']);

    // --- Administracion ---
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'abilities:admin', 'throttle:300,1'])
        ->group(function (): void {
            Route::post('inventory/adjustments', [InventoryAdjustmentController::class, 'store'])
                ->middleware('idempotency');

            Route::get('inventory/movements', [InventoryMovementController::class, 'index']);

            Route::post('orders/{number}/transitions', [OrderTransitionController::class, 'store'])
                ->middleware('idempotency');
        });
});
