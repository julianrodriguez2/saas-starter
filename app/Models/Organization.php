<?php

namespace App\Models;

use App\Services\PlatformCacheService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Billable;

class Organization extends Model
{
    use HasFactory;
    use HasUuids;
    use Billable;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'owner_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'plan_id',
        'trial_ends_at',
        'is_suspended',
        'suspended_at',
        'suspension_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if ($organization->plan_id !== null) {
                return;
            }

            $organization->plan_id = Plan::query()
                ->where('name', 'Free')
                ->value('id');
        });

        static::saved(function (Organization $organization): void {
            $cache = app(PlatformCacheService::class);
            $cache->forgetOrganization($organization->id);

            foreach ($organization->cacheUserIds() as $userId) {
                $cache->forgetOrganizationAccess($userId, $organization->id);
                $cache->forgetUserOrganizations($userId);
            }
        });

        static::deleted(function (Organization $organization): void {
            $cache = app(PlatformCacheService::class);
            $cache->forgetOrganization($organization->id);

            foreach ($organization->cacheUserIds() as $userId) {
                $cache->forgetOrganizationAccess($userId, $organization->id);
                $cache->forgetUserOrganizations($userId);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'is_suspended' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function owners(): BelongsToMany
    {
        return $this->users()
            ->where(function (Builder $query) {
                $query->where('organization_user.role', OrganizationUser::ROLE_OWNER)
                    ->orWhere('users.id', $this->owner_id);
            });
    }

    public function admins(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role', OrganizationUser::ROLE_ADMIN);
    }

    public function members(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role', OrganizationUser::ROLE_MEMBER);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(UsageEvent::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function stripeName(): string
    {
        return $this->name;
    }

    public function stripeEmail(): ?string
    {
        return $this->owner?->email;
    }

    public function isSuspended(): bool
    {
        return (bool) $this->is_suspended;
    }

    public function hasActivePaidSubscription(): bool
    {
        return $this->subscriptions()
            ->whereIn('stripe_status', ['trialing', 'active', 'past_due'])
            ->exists();
    }

    public function isOnFreePlan(): bool
    {
        if ($this->plan_id === null) {
            return true;
        }

        if ($this->relationLoaded('plan')) {
            return strtolower((string) $this->plan?->name) === 'free';
        }

        return Plan::query()
            ->whereKey($this->plan_id)
            ->whereRaw('lower(name) = ?', ['free'])
            ->exists();
    }

    public function isOnTrial(): bool
    {
        if ($this->trial_ends_at !== null && $this->trial_ends_at->isFuture()) {
            return true;
        }

        return $this->subscriptions()
            ->where('stripe_status', 'trialing')
            ->exists();
    }

    public function canPerformWrites(): bool
    {
        if (! config('platform.suspension.block_writes', true)) {
            return true;
        }

        return ! $this->isSuspended();
    }

    /**
     * @return list<int>
     */
    private function cacheUserIds(): array
    {
        $userIds = DB::table('organization_user')
            ->where('organization_id', $this->id)
            ->pluck('user_id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        if (is_numeric($this->owner_id)) {
            $userIds[] = (int) $this->owner_id;
        }

        $originalOwnerId = $this->getOriginal('owner_id');

        if (is_numeric($originalOwnerId)) {
            $userIds[] = (int) $originalOwnerId;
        }

        return array_values(array_unique(array_filter(
            $userIds,
            static fn (int $userId): bool => $userId > 0
        )));
    }
}
