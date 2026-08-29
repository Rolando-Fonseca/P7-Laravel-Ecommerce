<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

/**
 * Exporta el catálogo con la MISMA forma que devuelve la API a un JSON estático
 * que consume el storefront de Next.js.
 *
 * Vercel no ejecuta PHP, así que el escaparate no puede llamar a la API en vivo.
 * En vez de escribir datos de mentira en el frontend, se generan desde la base
 * de datos real usando los mismos API Resources: si el contrato cambia, este
 * archivo cambia con él.
 */
class ExportCatalog extends Command
{
    protected $signature = 'catalog:export {--path=storefront/src/data/catalog.json}';

    protected $description = 'Exporta el catálogo activo al JSON que consume el storefront';

    public function handle(): int
    {
        $products = Product::query()
            ->visible()
            ->with(['category', 'images', 'variants.inventoryItems'])
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            $this->error('No hay productos activos. Ejecuta php artisan db:seed primero.');

            return self::FAILURE;
        }

        $payload = [
            'generated_from' => 'GET /api/v1/products + GET /api/v1/products/{slug}',
            'currency' => 'COP',
            'categories' => Category::query()
                ->withCount(['products' => fn ($q) => $q->where('status', ProductStatus::Active)])
                ->orderBy('position')
                ->get()
                ->map(fn (Category $c): array => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'size_system' => $c->size_system->value,
                    'product_count' => (int) $c->products_count,
                ])->all(),
            'products' => $products->map(fn (Product $p): array => [
                'slug' => $p->slug,
                'name' => $p->name,
                'summary' => $p->summary,
                'description' => $p->description,
                'material' => $p->material,
                'care_instructions' => $p->care_instructions,
                'category' => ['slug' => $p->category->slug, 'name' => $p->category->name],
                'base_price_cents' => $p->base_price_cents,
                'price_range_cents' => [
                    'min' => (int) $p->variants->min(fn (ProductVariant $v): int => $v->effectivePriceCents()),
                    'max' => (int) $p->variants->max(fn (ProductVariant $v): int => $v->effectivePriceCents()),
                ],
                'available_sizes' => $p->variants->pluck('size')->unique()->values()->all(),
                'available_colors' => $p->variants->unique('color_name')->values()
                    ->map(fn (ProductVariant $v): array => [
                        'name' => $v->color_name,
                        'hex' => $v->color_hex,
                    ])->all(),
                'in_stock' => $p->variants->contains(fn (ProductVariant $v): bool => $v->available() > 0),
                'variant_count' => $p->variants->count(),
                'total_available' => (int) $p->variants->sum(fn (ProductVariant $v): int => $v->available()),
                'sample_variant' => $this->sampleVariant($p),
            ])->all(),
        ];

        $path = base_path((string) $this->option('path'));

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->info(sprintf(
            'Exportados %d productos y %d categorias a %s',
            count($payload['products']),
            count($payload['categories']),
            $path
        ));

        return self::SUCCESS;
    }

    /**
     * Una variante de ejemplo por producto: es la que el storefront usa para
     * enseñar la forma real de un SKU y su stock.
     *
     * @return array<string, mixed>|null
     */
    private function sampleVariant(Product $product): ?array
    {
        $variant = $product->variants->firstWhere(fn (ProductVariant $v): bool => $v->available() > 0)
            ?? $product->variants->first();

        if ($variant === null) {
            return null;
        }

        return [
            'sku' => $variant->sku,
            'size' => $variant->size,
            'color' => ['name' => $variant->color_name, 'hex' => $variant->color_hex],
            'price_cents' => $variant->effectivePriceCents(),
            'available' => $variant->available(),
        ];
    }
}
