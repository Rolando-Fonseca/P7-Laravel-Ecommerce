<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class CreateOrderRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cart_token' => ['required', 'uuid', 'exists:carts,token'],
            'email' => ['required', 'email', 'max:255'],

            'shipping_address' => ['required', 'array'],
            'shipping_address.full_name' => ['required', 'string', 'min:3', 'max:120'],
            'shipping_address.phone' => ['required', 'string', 'min:7', 'max:20'],
            'shipping_address.line1' => ['required', 'string', 'min:5', 'max:180'],
            'shipping_address.line2' => ['nullable', 'string', 'max:180'],
            'shipping_address.city' => ['required', 'string', 'min:2', 'max:80'],
            'shipping_address.state' => ['required', 'string', 'min:2', 'max:80'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:20'],
            // En el MVP solo Colombia. Aceptar cualquier ISO-3166 sin poder enviar
            // alli seria mentirle al cliente.
            'shipping_address.country' => ['required', 'string', 'in:CO'],

            // null significa "igual que el envio".
            'billing_address' => ['nullable', 'array'],
            'billing_address.full_name' => ['required_with:billing_address', 'string', 'min:3', 'max:120'],
            'billing_address.line1' => ['required_with:billing_address', 'string', 'min:5', 'max:180'],
            'billing_address.city' => ['required_with:billing_address', 'string', 'min:2', 'max:80'],
            'billing_address.state' => ['required_with:billing_address', 'string', 'min:2', 'max:80'],
            'billing_address.country' => ['required_with:billing_address', 'string', 'in:CO'],

            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
