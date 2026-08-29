<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Base de todos los FormRequest de la API.
 *
 * Aporta el rechazo de parametros de consulta desconocidos. Ignorarlos hace que el
 * cliente crea que filtro y reciba resultados sin filtrar: es el bug mas caro y mas
 * dificil de detectar de una API de catalogo. Quien escribe ?categoria=camisas
 * cuando el parametro es ?category= recibe el catalogo entero y no se entera.
 */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<int, string> */
    protected function allowedQueryParams(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $allowed = $this->allowedQueryParams();

        if ($allowed === []) {
            return;
        }

        $unknown = array_diff(array_keys($this->query()), $allowed);

        if ($unknown === []) {
            return;
        }

        throw ValidationException::withMessages(
            array_reduce(
                $unknown,
                function (array $carry, string $param): array {
                    $carry[$param] = 'parametro no reconocido';

                    return $carry;
                },
                []
            )
        );
    }
}
