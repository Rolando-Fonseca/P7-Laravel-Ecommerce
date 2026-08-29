<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class AdjustInventoryRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'exists:product_variants,sku'],
            'warehouse_code' => ['required', 'string', 'exists:warehouses,code'],
            // Distinto de 0: un ajuste que no ajusta nada es un error del cliente,
            // no una operacion valida.
            'quantity_delta' => ['required', 'integer', 'between:-10000,10000', 'not_in:0'],
            // Obligatorio. Un ajuste sin motivo es un descuadre que nadie puede
            // auditar seis meses despues.
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'quantity_delta.not_in' => 'El ajuste no puede ser de 0 unidades.',
            'reason.required' => 'Todo ajuste de inventario necesita un motivo auditable.',
        ];
    }
}
