<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class TransitionOrderRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'in:paid,packed,shipped,cancelled,returned'],
            // Cancelar o aceptar una devolucion sin motivo deja un hueco en la
            // trazabilidad que nadie puede reconstruir despues.
            'reason' => ['nullable', 'string', 'max:255', 'required_if:to,cancelled', 'required_if:to,returned'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required_if' => 'Cancelar o devolver un pedido requiere indicar el motivo.',
        ];
    }
}
