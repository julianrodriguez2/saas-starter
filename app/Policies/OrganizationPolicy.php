<?php

namespace App\Policies;

use App\Models\Organization;
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
}
