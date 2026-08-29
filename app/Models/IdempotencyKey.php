<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'key', 'endpoint', 'request_hash',
        'response_status', 'response_body', 'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'response_status' => 'integer',
            'locked_at' => 'datetime',
        ];
    }
}
