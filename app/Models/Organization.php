<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'owner_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'plan_id',
        'trial_ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
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
}
