<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        if ($organization->owner_id === $user->id) {
            return true;
        }

        return $organization->users()
            ->where('users.id', $user->id)
            ->exists();
    }

    public function update(User $user, Organization $organization): bool
    {
        if ($organization->owner_id === $user->id) {
            return true;
        }

        return $organization->users()
            ->where('users.id', $user->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        if ($user->isOwner($organization)) {
            return true;
        }

        return $user->roleInOrganization($organization) === OrganizationUser::ROLE_ADMIN;
    }
}
