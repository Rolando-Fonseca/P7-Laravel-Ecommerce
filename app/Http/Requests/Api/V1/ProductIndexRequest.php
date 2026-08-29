<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class ProductIndexRequest extends ApiFormRequest
{
    /** @return array<int, string> */
    protected function allowedQueryParams(): array
    {
        return ['page', 'per_page', 'category', 'size', 'color', 'price_min', 'price_max', 'q', 'in_stock', 'sort'];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['integer', 'min:1', 'max:10000'],
            // Fuera de rango es 422, no se recorta en silencio: recortar oculta un
            // bug del cliente.
            'per_page' => ['integer', 'min:1', 'max:100'],
            'category' => ['string', 'exists:categories,slug'],
            'size' => ['string', 'max:60'],
            'color' => ['string', 'max:120'],
            'price_min' => ['integer', 'min:0', 'max:100000000'],
            // gte:price_min solo cuando price_min viene. Sin la condicion, un
            // ?price_max= suelto compara contra null y devuelve 422 por un filtro
            // que es perfectamente valido.
            'price_max' => array_filter([
                'integer', 'min:0', 'max:100000000',
                $this->has('price_min') ? 'gte:price_min' : null,
            ]),
            'q' => ['string', 'min:2', 'max:80'],
            'in_stock' => ['in:true,false'],
            'sort' => ['in:name,-name,price,-price,created_at,-created_at'],
        ];
    }
}
