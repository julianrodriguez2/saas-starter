<?php

namespace App\Models;

use App\Services\PlatformCacheService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (Plan $plan): void {
            app(PlatformCacheService::class)->forgetPlanLimits($plan->id);
        });

        static::deleted(function (Plan $plan): void {
            app(PlatformCacheService::class)->forgetPlanLimits($plan->id);
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'stripe_price_id',
        'limits',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limits' => 'array',
        ];
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }
}
