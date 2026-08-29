<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\CartItem;

class AddCartItemRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'exists:product_variants,sku'],
            // El tope no es antojo: sin limite, un script mete 999999999 y revienta
            // el unsignedInteger al calcular el total de linea.
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartItem::MAX_QUANTITY],
        ];
    }
}
