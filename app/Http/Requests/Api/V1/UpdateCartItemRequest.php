<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\CartItem;

class UpdateCartItemRequest extends ApiFormRequest
{
    /**
     * Reemplaza la cantidad, no la suma. Para eliminar existe DELETE: un 0 que
     * significa "borrar" es un caso especial escondido dentro de una actualizacion,
     * y siempre se olvida en algun cliente.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartItem::MAX_QUANTITY],
        ];
    }
}
