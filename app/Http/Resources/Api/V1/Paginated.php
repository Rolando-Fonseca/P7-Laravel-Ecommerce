<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * El bloque meta + links de docs/api/contracts/00-convenciones.md, identico en todos
 * los endpoints de coleccion.
 *
 * prev y next son null en los extremos, nunca se omiten: un cliente que hace
 * if (links.next) no debe tener que distinguir entre "ausente" y "nulo".
 */
final class Paginated
{
    /**
     * @param  array<int, mixed>|AnonymousResourceCollection  $data
     * @return array<string, mixed>
     */
    public static function wrap(LengthAwarePaginator $paginator, mixed $data): array
    {
        $lastPage = max(1, $paginator->lastPage());
        $current = $paginator->currentPage();

        return [
            'data' => $data,
            'meta' => [
                'page' => $current,
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $lastPage,
            ],
            'links' => [
                'self' => $paginator->url($current),
                'first' => $paginator->url(1),
                'prev' => $current > 1 ? $paginator->url($current - 1) : null,
                'next' => $current < $lastPage ? $paginator->url($current + 1) : null,
                'last' => $paginator->url($lastPage),
            ],
        ];
    }
}
