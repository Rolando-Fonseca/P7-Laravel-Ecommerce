<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class ProductQueryService
{
    /** Lista blanca de ordenamientos. Nunca una lista negra: aceptar cualquier
     *  nombre de columna abre la puerta a ordenar por columnas sin indice.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORTS = [
        'name' => ['name', 'asc'],
        '-name' => ['name', 'desc'],
        'price' => ['base_price_cents', 'asc'],
        '-price' => ['base_price_cents', 'desc'],
        'created_at' => ['created_at', 'asc'],
        '-created_at' => ['created_at', 'desc'],
    ];

    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->visible()
            // Sin esto son tres consultas por producto en el listado.
            ->with(['category', 'primaryImage']);

        $this->applyCategory($query, $filters);
        $this->applySizeAndColor($query, $filters);
        $this->applyPriceRange($query, $filters);
        $this->applySearch($query, $filters);
        $this->applyInStock($query, $filters);

        [$column, $direction] = self::SORTS[$filters['sort'] ?? '-created_at'];
        $query->orderBy($column, $direction)->orderBy('id');

        return $query->paginate(
            perPage: (int) ($filters['per_page'] ?? 20),
            page: (int) ($filters['page'] ?? 1),
        )->withQueryString();
    }

    /** @param  Builder<Product>  $query */
    private function applyCategory(Builder $query, array $filters): void
    {
        if (! isset($filters['category'])) {
            return;
        }

        $query->whereHas('category', fn (Builder $q) => $q->where('slug', $filters['category']));
    }

    /** @param  Builder<Product>  $query */
    private function applySizeAndColor(Builder $query, array $filters): void
    {
        if (isset($filters['size'])) {
            $sizes = $this->toList($filters['size']);
            $query->whereHas('variants', fn (Builder $q) => $q->where('status', 'active')->whereIn('size', $sizes));
        }

        if (isset($filters['color'])) {
            $colors = array_map(
                fn (string $c): string => Str::lower(Str::ascii($c)),
                $this->toList($filters['color'])
            );

            $query->whereHas('variants', function (Builder $q) use ($colors): void {
                $q->where('status', 'active')->where(function (Builder $inner) use ($colors): void {
                    foreach ($colors as $color) {
                        $inner->orWhereRaw('LOWER(color_name) LIKE ?', ["%{$color}%"]);
                    }
                });
            });
        }
    }

    /**
     * El filtro que se rompe siempre.
     *
     * Consultar solo products.base_price_cents ignora las variantes con precio
     * propio y devuelve resultados incorrectos. Hay que evaluar
     * COALESCE(variante, producto).
     *
     * @param  Builder<Product>  $query
     */
    private function applyPriceRange(Builder $query, array $filters): void
    {
        $min = $filters['price_min'] ?? null;
        $max = $filters['price_max'] ?? null;

        if ($min === null && $max === null) {
            return;
        }

        $query->whereHas('variants', function (Builder $q) use ($min, $max): void {
            $q->where('product_variants.status', 'active');

            $expression = 'COALESCE(product_variants.price_cents, products.base_price_cents)';

            if ($min !== null) {
                $q->whereRaw("{$expression} >= ?", [(int) $min]);
            }

            if ($max !== null) {
                $q->whereRaw("{$expression} <= ?", [(int) $max]);
            }
        });
    }

    /** @param  Builder<Product>  $query */
    private function applySearch(Builder $query, array $filters): void
    {
        if (! isset($filters['q'])) {
            return;
        }

        // ADR-0009: LIKE en el MVP. No usa indices y no tolera errores de escritura.
        // Se cambia cuando el catalogo pase de 2.000 productos.
        $term = '%'.Str::lower((string) $filters['q']).'%';

        $query->where(function (Builder $q) use ($term): void {
            $q->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(description, "")) LIKE ?', [$term]);
        });
    }

    /** @param  Builder<Product>  $query */
    private function applyInStock(Builder $query, array $filters): void
    {
        if (! isset($filters['in_stock'])) {
            return;
        }

        $wantsStock = filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN);

        // El filtro mas caro del endpoint. EXISTS y no whereHas anidado.
        $has = fn (Builder $q) => $q->whereHas('variants', fn (Builder $v) => $v
            ->where('status', 'active')
            ->whereHas('inventoryItems', fn (Builder $i) => $i
                ->whereRaw('quantity_on_hand - quantity_reserved > 0')));

        $wantsStock ? $has($query) : $query->whereDoesntHave('variants', fn (Builder $v) => $v
            ->where('status', 'active')
            ->whereHas('inventoryItems', fn (Builder $i) => $i
                ->whereRaw('quantity_on_hand - quantity_reserved > 0')));
    }

    /** @return array<int, string> */
    private function toList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
