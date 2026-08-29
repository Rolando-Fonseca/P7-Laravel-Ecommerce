<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Una camisa usa S-XXL, un pantalón usa la cintura en pulgadas y un zapato la talla
 * europea. Guardar la talla como texto y su sistema por separado evita una columna
 * por tipo de prenda.
 */
enum SizeSystem: string
{
    case Alpha = 'alpha';
    case Waist = 'waist';
    case EuShoe = 'eu_shoe';
    case Unica = 'unica';

    /** @return array<int, string> */
    public function values(): array
    {
        return match ($this) {
            self::Alpha => ['S', 'M', 'L', 'XL', 'XXL'],
            self::Waist => ['28', '30', '32', '34', '36', '38', '40'],
            self::EuShoe => ['39', '40', '41', '42', '43', '44', '45', '46'],
            self::Unica => ['U'],
        };
    }
}
