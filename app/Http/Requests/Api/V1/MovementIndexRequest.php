<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class MovementIndexRequest extends ApiFormRequest
{
    /** @return array<int, string> */
    protected function allowedQueryParams(): array
    {
        return ['page', 'per_page', 'sku', 'warehouse_code', 'type', 'from', 'to', 'sort'];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['integer', 'min:1', 'max:10000'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'sku' => ['string', 'exists:product_variants,sku'],
            'warehouse_code' => ['string', 'exists:warehouses,code'],
            'type' => ['string', 'max:120'],
            'from' => ['date'],
            'to' => array_filter([
                'date',
                $this->has('from') ? 'after_or_equal:from' : null,
            ]),
            'sort' => ['in:created_at,-created_at'],
        ];
    }
}
