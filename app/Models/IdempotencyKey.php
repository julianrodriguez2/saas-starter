<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'scope',
        'key',
        'fingerprint',
        'processed_at',
        'response_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'response_payload' => 'array',
        ];
    }
}
