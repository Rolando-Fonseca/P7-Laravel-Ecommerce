<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Context;

final class TraceId
{
    public const KEY = 'trace_id';

    public static function current(): string
    {
        return (string) (Context::get(self::KEY) ?? '');
    }
}
