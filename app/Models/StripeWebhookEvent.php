<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'stripe_event_id',
        'type',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
