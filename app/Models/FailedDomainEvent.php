<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedDomainEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'event_key',
        'event_type',
        'payload',
        'error_message',
        'failed_at',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'failed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
