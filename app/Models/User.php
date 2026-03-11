<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(OrganizationUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    public function roleInOrganization(Organization|string|null $organization): ?string
    {
        $organizationId = $organization instanceof Organization
            ? $organization->getKey()
            : $organization;

        if (! is_string($organizationId) || $organizationId === '') {
            return null;
        }

        if ($this->ownedOrganizations()->whereKey($organizationId)->exists()) {
            return OrganizationUser::ROLE_OWNER;
        }

        return $this->organizations()
            ->whereKey($organizationId)
            ->value('organization_user.role');
    }

    public function isOwner(Organization|string|null $organization): bool
    {
        return $this->roleInOrganization($organization) === OrganizationUser::ROLE_OWNER;
    }

    public function isAdmin(Organization|string|null $organization): bool
    {
        return $this->roleInOrganization($organization) === OrganizationUser::ROLE_ADMIN;
    }

    public function belongsToOrganization(Organization|string|null $organization): bool
    {
        return $this->roleInOrganization($organization) !== null;
    }

    public function isSuperAdmin(): bool
    {
        $email = strtolower(trim((string) $this->email));

        if ($email === '') {
            return false;
        }

        $superAdminEmails = config('platform.super_admin_emails', config('admin.super_admin_emails', []));

        if (! is_array($superAdminEmails)) {
            return false;
        }

        return in_array($email, $superAdminEmails, true);
    }
}
