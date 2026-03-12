<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;

trait InteractsWithTenantTestData
{
    protected function seedPlans(): void
    {
        $this->seed(PlanSeeder::class);
    }

    protected function freePlan(): Plan
    {
        return Plan::query()
            ->whereRaw('lower(name) = ?', ['free'])
            ->firstOrFail();
    }

    protected function proPlan(): Plan
    {
        return Plan::query()
            ->whereRaw('lower(name) = ?', ['pro'])
            ->firstOrFail();
    }

    protected function enterprisePlan(): Plan
    {
        return Plan::query()
            ->whereRaw('lower(name) = ?', ['enterprise'])
            ->firstOrFail();
    }

    protected function createOrganizationWithOwner(
        ?User $owner = null,
        ?Plan $plan = null,
        ?string $name = null
    ): Organization {
        $owner ??= User::factory()->create();

        return Organization::factory()
            ->for($owner, 'owner')
            ->create([
                'name' => $name ?? "{$owner->name}'s Organization",
                'plan_id' => $plan?->id,
            ]);
    }

    protected function addOrganizationMember(
        Organization $organization,
        User $user,
        string $role = OrganizationUser::ROLE_MEMBER
    ): void {
        $organization->users()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    protected function actingAsInOrganization(User $user, Organization $organization): static
    {
        return $this
            ->actingAs($user)
            ->withSession([
                'organization_id' => $organization->id,
            ]);
    }

    protected function makeSuperAdmin(User $user): void
    {
        config()->set('platform.super_admin_emails', [
            strtolower((string) $user->email),
        ]);
    }
}
