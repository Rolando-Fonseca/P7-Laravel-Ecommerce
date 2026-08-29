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
}
