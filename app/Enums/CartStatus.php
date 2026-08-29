<?php

declare(strict_types=1);

namespace App\Enums;

enum CartStatus: string
{
    case Open = 'open';
    case Converted = 'converted';
    case Abandoned = 'abandoned';

    public function isModifiable(): bool
    {
        return $this === self::Open;
    }
}
