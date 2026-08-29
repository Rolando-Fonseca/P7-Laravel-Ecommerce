<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class AvailabilityRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'skus' => ['required', 'array', 'min:1', 'max:50'],
            'skus.*' => ['string', 'min:5', 'max:40'],
        ];
    }
}
